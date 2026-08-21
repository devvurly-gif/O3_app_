<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Neutralise Vite pour toute la suite.
     *
     * `welcome.blade.php` porte une directive `@vite`, qui exige soit le
     * serveur de développement (`public/hot`), soit un manifeste construit
     * (`public/build/manifest.json`). En local les deux existent, donc la
     * suite passe. Le job `phpunit` de la CI, lui, ne lance jamais
     * `npm run build` — c'est un job séparé — et rendait donc une 500 sur
     * toute requête atteignant la vue.
     *
     * C'est la seule et unique cause de l'échec du workflow Tests, et elle
     * n'a rien à voir avec l'application : le déploiement automatique était
     * bloqué depuis des mois par un test qui rend une page HTML sans que ses
     * assets aient été construits.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
