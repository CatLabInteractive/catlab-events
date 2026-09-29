<?php

namespace Tests\Integration\Cms;

use Carbon\Carbon;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;
use Tests\Integration\IntegrationTestCase;

/**
 * The blog's chrome strings come from resources/lang/{nl,en,fr}/cms.php and
 * its dates are formatted in the locale of the URL.
 */
class LocaleTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    private function trilingualPost(): void
    {
        $organisation = $this->createOrganisation();
        $post = $this->createPost($organisation, 'quiz', Carbon::create(2019, 3, 14, 20, 0, 0, 'Europe/Brussels'));
        $this->createPostTranslation($post, 'en', 'quiz-night');
        $this->createPostTranslation($post, 'fr', 'soiree-quiz');
    }

    public function testDutchBlog()
    {
        $this->trilingualPost();

        $response = $this->get('/blog');
        $response->assertStatus(200);
        $response->assertSee('<html lang="nl">', false);
        $response->assertSee('<title>Blog – Test organisation</title>', false);
        $response->assertSee('Lees meer');
        $response->assertSee('14 maart 2019');

        $this->get('/2019/03/14/quiz')->assertSee('Gepubliceerd op')->assertSee('14 maart 2019');
    }

    public function testEnglishBlog()
    {
        $this->trilingualPost();

        $response = $this->get('/en/blog');
        $response->assertStatus(200);
        $response->assertSee('<html lang="en">', false);
        $response->assertSee('Read more');
        $response->assertSee('14 March 2019');
        $response->assertDontSee('Lees meer');
        $response->assertDontSee('maart');

        $response = $this->get('/en/2019/03/14/quiz-night');
        $response->assertSee('<html lang="en">', false);
        $response->assertSee('Published on');
        $response->assertSee('14 March 2019');
        $response->assertSee('Also available in');
    }

    public function testFrenchBlog()
    {
        $this->trilingualPost();

        $response = $this->get('/fr/blog');
        $response->assertStatus(200);
        $response->assertSee('<html lang="fr">', false);
        $response->assertSee('Lire la suite');
        $response->assertSee('14 mars 2019');

        $this->get('/fr/2019/03/14/soiree-quiz')->assertSee('Publié le', false)->assertSee('14 mars 2019');
    }
}
