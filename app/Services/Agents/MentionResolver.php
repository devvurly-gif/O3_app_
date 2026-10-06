<?php

namespace App\Services\Agents;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Retrouve, dans une phrase libre, le client, le fournisseur ou le produit dont on parle (« Atlas me doit combien ? »,
 * « combien reste-t-il de perceuses ? »), et le reformule en commande connue (« fiche du client … »). Lecture seule :
 * il ne fait que chercher dans la base et ne s'applique qu'à une phrase que les autres règles n'ont pas comprise.
 */
class MentionResolver
{
    private const STOP = [
        'quel', 'quels', 'quelle', 'quelles', 'combien', 'reste', 'restes', 'restent', 'stock', 'stocks', 'prix', 'disponible', 'disponibles', 'produit', 'produits', 'article', 'articles',
        'magasin', 'depot', 'entrepot', 'encore', 'avons', 'avez', 'notre', 'votre', 'dans', 'pour', 'avec', 'sont', 'cette', 'cela', 'tous', 'toutes', 'faible', 'total', 'mon', 'mes', 'est',
        'les', 'des', 'une', 'que', 'qui', 'quoi', 'donne', 'moi', 'montre', 'dis', 'voir', 'veux', 'peux', 'chez', 'sur', 'plus', 'moins', 'vendre', 'vend', 'achete',
    ];

    /** Le tiers (client ou fournisseur actif) nommé dans la phrase ; le nom le plus long l'emporte. @param string $n phrase normalisée */
    public function thirdParty(string $n): ?object
    {
        $best = null;
        foreach (DB::table('third_partners')->whereNull('deleted_at')->where('tp_status', true)->get(['id', 'tp_title', 'tp_Role']) as $t) {
            $name = trim(preg_replace('/[^a-z0-9 ]+/', ' ', mb_strtolower(Str::ascii((string) $t->tp_title))) ?? '');
            if (mb_strlen($name) < 3 || !preg_match('/(?<![a-z0-9])' . preg_quote($name, '/') . '(?![a-z0-9])/', $n)) {
                continue;
            }
            if ($best === null || mb_strlen($name) > $best[0]) {
                $best = [mb_strlen($name), $t, $name];
            }
        }

        return $best === null ? null : (object) ['id' => $best[1]->id, 'title' => $best[1]->tp_title, 'role' => $best[1]->tp_Role, 'name' => $best[2]];
    }

    /** Le seul tiers actif dont le nom contient ce mot (« bati » → « Bati Matériaux ») ; null s'il y en a zéro ou plusieurs. */
    public function thirdPartyLike(string $term): ?object
    {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        $rows = DB::table('third_partners')->whereNull('deleted_at')->where('tp_status', true)->where('tp_title', 'like', $like)->limit(2)->get(['id', 'tp_title', 'tp_Role']);
        if ($rows->count() !== 1) {
            return null;
        }
        $t = $rows->first();

        return (object) ['id' => $t->id, 'title' => $t->tp_title, 'role' => $t->tp_Role, 'name' => trim(preg_replace('/[^a-z0-9 ]+/', ' ', mb_strtolower(Str::ascii((string) $t->tp_title))) ?? '')];
    }
    /** Le premier mot de la phrase qui désigne un produit (titre ou référence), ou null. @param string $n phrase normalisée */
    public function productWord(string $n): ?string
    {
        foreach (preg_split('/[^a-z0-9\-]+/', $n) ?: [] as $word) {
            if (mb_strlen($word) < 4 || in_array($word, self::STOP, true)) {
                continue;
            }
            $stem = preg_replace('/(s|x)$/', '', $word) ?? $word;
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $stem) . '%';
            if (DB::table('products')->whereNull('deleted_at')->where(fn ($w) => $w->where('p_title', 'like', $like)->orWhere('p_sku', 'like', $like))->exists()) {
                return $stem;
            }
        }

        return null;
    }
}
