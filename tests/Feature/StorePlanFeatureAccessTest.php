<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Store;
use App\Models\StorePlanFeatureOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorePlanFeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_disabled_in_published_blocks_even_store_owner(): void
    {
        $plan = Plan::create([
            'name' => 'Basico',
            'slug' => 'basic',
            'max_stores' => 3,
            'max_employees' => 10,
            'price' => 0,
        ]);

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        DB::table('store_user')->insert([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permission = Permission::create([
            'slug' => 'subscriptions.view',
            'name' => 'Ver suscripciones',
        ]);

        $feature = PlanFeature::create([
            'slug' => 'subscriptions.module',
            'module' => 'subscriptions',
            'name' => 'Modulo Suscripciones',
        ]);
        $permission->planFeatures()->sync([$feature->id]);

        StorePlanFeatureOverride::create([
            'store_id' => $store->id,
            'plan_feature_id' => $feature->id,
            'scope' => 'published',
            'status' => 'disabled',
            'updated_by_user_id' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('stores.subscriptions.plans', $store));

        $response->assertForbidden();
    }

    public function test_disabled_feature_blocks_access_even_with_preview_session_flag(): void
    {
        $plan = Plan::create([
            'name' => 'Basico',
            'slug' => 'basic',
            'max_stores' => 3,
            'max_employees' => 10,
            'price' => 0,
        ]);

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        DB::table('store_user')->insert([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permission = Permission::create([
            'slug' => 'subscriptions.view',
            'name' => 'Ver suscripciones',
        ]);

        $feature = PlanFeature::create([
            'slug' => 'subscriptions.module',
            'module' => 'subscriptions',
            'name' => 'Modulo Suscripciones',
        ]);
        $permission->planFeatures()->sync([$feature->id]);

        StorePlanFeatureOverride::create([
            'store_id' => $store->id,
            'plan_feature_id' => $feature->id,
            'scope' => 'published',
            'status' => 'disabled',
            'updated_by_user_id' => $owner->id,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['plan_features.preview_all' => true])
            ->get(route('stores.subscriptions.plans', $store));

        $response->assertForbidden();
    }
}

