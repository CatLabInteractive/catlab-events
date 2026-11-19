<?php

namespace Tests\Integration;

use App\Models\Organisation;
use App\Models\OrganisationDomain;
use App\Models\User;
use Tests\Integration\Concerns\CreatesCmsFixtures;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Admins manage the domains of their organisation ("Domeinen"), with one
 * canonical domain and without cutting off the domain they work on.
 */
class OrganisationDomainAdminTest extends IntegrationTestCase
{
    use CreatesEventFixtures;
    use CreatesCmsFixtures;

    /** @var Organisation */
    private $organisation;

    /** @var User */
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = $this->createOrganisation();
        $this->admin = $this->createOrganisationAdmin($this->organisation);
    }

    public function testAnAdminAddsEditsAndDeletesADomain()
    {
        $this->actingAs($this->admin)->get('/admin/domains')->assertStatus(200);
        $this->actingAs($this->admin)->get('/admin/domains/create')
            ->assertStatus(200)
            ->assertSee('name="fields[domain]"', false);

        // Pasted as an address: stored as the bare host name.
        $this->actingAs($this->admin)->post('/admin/domains', [ 'fields' => [
            'domain' => 'https://Www.Example.test/over-ons',
            'is_canonical' => '1',
        ] ])->assertRedirect(action('Admin\DomainController@index'));

        $domain = $this->organisation->domains()->first();
        $this->assertNotNull($domain);
        $this->assertSame('www.example.test', $domain->domain);
        $this->assertTrue($domain->is_canonical);

        $this->actingAs($this->admin)->get('/admin/domains')->assertSee('www.example.test');

        $this->actingAs($this->admin)->put("/admin/domains/{$domain->id}", [ 'fields' => [
            'domain' => 'tickets.example.test',
            'is_canonical' => '0',
        ] ])->assertRedirect(action('Admin\DomainController@index'));

        $domain->refresh();
        $this->assertSame('tickets.example.test', $domain->domain);
        $this->assertFalse($domain->is_canonical);

        $this->actingAs($this->admin)->delete("/admin/domains/{$domain->id}")
            ->assertRedirect(action('Admin\DomainController@index'));
        $this->assertNull(OrganisationDomain::find($domain->id));
    }

    public function testMarkingADomainCanonicalClearsTheOthers()
    {
        $old = $this->createOrganisationDomain($this->organisation, 'old.example.test');
        $old->is_canonical = true;
        $old->save();

        // Another organisation's canonical domain is left alone.
        $other = $this->createOrganisation();
        $otherDomain = $this->createOrganisationDomain($other, 'www.other.test');
        $otherDomain->is_canonical = true;
        $otherDomain->save();

        $this->actingAs($this->admin)->post('/admin/domains', [ 'fields' => [
            'domain' => 'www.example.test',
            'is_canonical' => '1',
        ] ])->assertRedirect(action('Admin\DomainController@index'));

        $this->assertFalse($old->fresh()->is_canonical);
        $this->assertTrue($this->organisation->domains()->where('domain', 'www.example.test')->first()->is_canonical);
        $this->assertTrue($otherDomain->fresh()->is_canonical);
        $this->assertSame(1, $this->organisation->domains()->where('is_canonical', true)->count());
    }

    public function testADomainIsValidAndUniqueAcrossOrganisations()
    {
        $other = $this->createOrganisation();
        $this->createOrganisationDomain($other, 'www.other.test');

        $this->actingAs($this->admin)->post('/admin/domains', [ 'fields' => [
            'domain' => 'www.other.test',
        ] ])->assertSessionHasErrors([ 'fields.domain' => 'Dit domein is al in gebruik.' ]);

        $this->actingAs($this->admin)->post('/admin/domains', [ 'fields' => [
            'domain' => 'not a domain',
        ] ])->assertSessionHasErrors('fields.domain');

        $this->assertSame(0, $this->organisation->domains()->count());
    }

    public function testTheDomainTheAdminIsOnCannotBeDeletedOrRenamed()
    {
        $current = $this->createOrganisationDomain($this->organisation, 'tickets.example.test');
        $this->actAsHost('tickets.example.test');

        $this->actingAs($this->admin)->delete("http://tickets.example.test/admin/domains/{$current->id}")
            ->assertSessionHas('message', function ($message) {
                return str_contains((string) $message, 'Je werkt nu op dit domein.');
            });
        $this->assertNotNull(OrganisationDomain::find($current->id));

        $this->actingAs($this->admin)->put("http://tickets.example.test/admin/domains/{$current->id}", [ 'fields' => [
            'domain' => 'elsewhere.example.test',
        ] ])->assertSessionHasErrors('fields.domain');
        $this->assertSame('tickets.example.test', $current->fresh()->domain);

        // Marking it canonical (without renaming) is fine.
        $this->actingAs($this->admin)->put("http://tickets.example.test/admin/domains/{$current->id}", [ 'fields' => [
            'domain' => 'tickets.example.test',
            'is_canonical' => '1',
        ] ])->assertRedirect();
        $this->assertTrue($current->fresh()->is_canonical);
    }

    public function testAnotherOrganisationsDomainIsNotReachable()
    {
        $other = $this->createOrganisation();
        $domain = $this->createOrganisationDomain($other, 'www.other.test');

        $this->actingAs($this->admin)->get("/admin/domains/{$domain->id}/edit")->assertStatus(404);
        $this->actingAs($this->admin)->delete("/admin/domains/{$domain->id}")->assertStatus(404);
        $this->assertNotNull(OrganisationDomain::find($domain->id));
    }

    public function testTheAdminIsNotRedirectedToTheCanonicalDomain()
    {
        $this->createOrganisationDomain($this->organisation, 'tickets.example.test');
        $canonical = $this->createOrganisationDomain($this->organisation, 'www.example.test');
        $canonical->is_canonical = true;
        $canonical->save();

        config([ 'seo.canonical_redirect' => true ]);
        $this->actAsHost('tickets.example.test');

        $this->actingAs($this->admin)->get('http://tickets.example.test/admin/domains')->assertStatus(200);
    }
}
