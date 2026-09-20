<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteur des numeros de facture d'abonnement, une ligne par annee.
 *
 * Une table dediee plutot qu'un `MAX(number) + 1` : deux emissions simultanees
 * — le cron du matin et un clic au back-office — liraient le meme maximum et
 * produiraient deux fois le meme numero. Ici, InvoiceNumberService verrouille
 * la ligne de l'annee le temps de l'incrementer, ce qui rend la sequence
 * continue et sans doublon, comme la loi l'exige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->unique();   // ex. « FA-2026 »
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
