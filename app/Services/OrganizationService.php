<?php

namespace App\Services;

use App\Http\Resources\OrganizationsResource;
use App\Http\Resources\BillingResource;
use App\Http\Resources\UserResource;
use App\Models\BillingCredit;
use App\Models\BillingDebit;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingTransaction;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use DB;
use Str;
use Propaganistas\LaravelPhone\PhoneNumber;

class OrganizationService
{
    /**
     * Get all organizations based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function get(object $request, $userId = null)
    {
        $organizations = (new Organization)->listAll($request->query('search'), $userId);

        return OrganizationsResource::collection($organizations);
    }

    /**
     * Retrieve an organization by its UUID.
     *
     * @param string $uuid
     * @return \App\Models\Organization
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getByUuid($request, $uuid = null)
    {
        $result['plans'] = SubscriptionPlan::all();

        if ($uuid === null) {
            $result['organization'] = null;
            $result['billing'] = null;
            $result['users'] = null;
    
            return $result;
        }

        $organization = Organization::with('subscription.plan')->where('uuid', $uuid)->first();
        $users = (new User)->listAll('user', $request->query('search'), $organization->id);
        $billing = (new BillingTransaction)->listAll($request->query('search'), $organization->id);
        
        $result['organization'] = $organization;
        $result['billing'] = BillingResource::collection($billing);
        $result['users'] = UserResource::collection($users);

        return $result;
    }

    /**
     * Store a new organization based on the provided request data.
     *
     * @param Request $request
     */
    public function store(Object $request)
    {
      return DB::transaction(function () use ($request) {
            if ($request->input('create_user') == 1) {
                // Create and attach user to organization
                $user = User::create([
                    'first_name' => $request->input('first_name'),
                    'last_name' => $request->input('last_name'),
                    'email' => $request->input('email'),
                    'role' => 'user',
                    'phone' => $request->input('phone') ? phone($request->input('phone'))->formatE164() : null,
                    'address' => json_encode([
                        'street' => $request->input('street'),
                        'city' => $request->input('city'),
                        'state' => $request->input('state'),
                        'zip' => $request->input('zip'),
                        'country' => $request->input('country'),
                    ]),
                    'password' => $request->input('password'),
                ]);
            } else {
                // Attach existing user to organization
                $user = User::where('email', $request->input('email'))->first();
            }

            $timestamp = now()->format('YmdHis');
            $randomString = Str::random(4);
            $userId = $user->id;

            $organization = Organization::create([
                'name' => $request->input('name'),
                'identifier' => $timestamp . $userId . $randomString,
                'address' => json_encode([
                    'street' => $request->street,
                    'city' => $request->city,
                    'state' => $request->state,
                    'zip' => $request->zip,
                    'country' => $request->country,
                ]),
                'created_by' => auth()->user()->id,
            ]);

            Team::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'created_by' => auth()->user()->id,
            ]);

            // حاول معرفة الشركة الأم عن طريق المستخدم الحالي
            $parentTeam = Team::where('user_id', auth()->user()->id)->first();
            $parentOrg = $parentTeam ? Organization::find($parentTeam->organization_id) : null;

            if ($parentOrg) {
                // جلب اشتراك المنظمة الأم (آخر اشتراك)
                $parentSubscription = Subscription::where('organization_id', $parentOrg->id)->latest()->first();

                if ($parentSubscription) {
                    Subscription::create([
                        'organization_id' => $organization->id,
                        'status' => $parentSubscription->status,
                        'plan_id' => $parentSubscription->plan_id,
                        'start_date' => now(),
                        'valid_until' => $parentSubscription->valid_until,
                    ]);
                }
            } else {
                // إذا لم يوجد منظمة أم، استخدم إعدادات الخطة التجريبية
                $plan = SubscriptionPlan::where('uuid', $request->plan)->first();
                $config = Setting::where('key', 'trial_period')->first();
                $has_trial = isset($config->value) && $config->value > 0;

                Subscription::create([
                    'organization_id' => $organization->id,
                    'status' => $has_trial ? 'trial' : 'active',
                    'plan_id' => $plan ? $plan->id : null,
                    'start_date' => now(),
                    'valid_until' => $has_trial ? now()->addDays($config->value) : now(),
                ]);
            }

            return $organization;
        });
    }

    /**
     * Update organization.
     *
     * @param Request $request
     * @param string $uuid
     * @return \App\Models\Organization
     */
    public function update($request, $uuid)
    {
        $user = auth()->user();
        $firstOrganization = $user->organizations()->orderBy('created_at')->first();

        $organization = Organization::where('uuid', $uuid)->firstOrFail();
        if ($organization->id !== $firstOrganization->id) {
            $organization->update([
                'name' => $request->input('name'),
                'address' => json_encode([
                    'street' => $request->street,
                    'city' => $request->city,
                    'state' => $request->state,
                    'zip' => $request->zip,
                    'country' => $request->country,
                ]),
            ]);

            return $organization;
        }

        // تحديث بيانات أول منظمة
        $organization->update([
            'name' => $request->input('name'),
            'address' => json_encode([
                'street' => $request->street,
                'city' => $request->city,
                'state' => $request->state,
                'zip' => $request->zip,
                'country' => $request->country,
            ]),
        ]);

        $subscription = Subscription::where('organization_id', $organization->id)->first();
        $plan = SubscriptionPlan::where('uuid', $request->plan)->first();

        if ($subscription) {
            $subscription->update([
                'plan_id' => $plan->id,
                'valid_until' => now()->addDays($plan->duration ?? 30), // أو حسب منطقك
            ]);
        } else {
            $config = Setting::where('key', 'trial_period')->first();
            $has_trial = isset($config->value) && $config->value > 0;
            $validUntil = $has_trial ? now()->addDays($config->value) : now()->addDays($plan->duration ?? 30);

            $subscription = Subscription::create([
                'organization_id' => $organization->id,
                'status' => $has_trial ? 'trial' : 'active',
                'plan_id' => $plan->id,
                'start_date' => now(),
                'valid_until' => $validUntil,
            ]);
        }

        if ($plan->id === 2) {
            $creatorId = $organization->created_by;
            if ($creatorId) {
                $creator = User::find($creatorId);
                if ($creator && $creator->max_organizations !== -1) {
                    $creator->max_organizations = -1;
                    $creator->save();
                }
            }
        }

        return $organization;

    }

    public function storeTransaction($request, $uuid){
        return DB::transaction(function () use ($request, $uuid) {
            $organization = Organization::where('uuid', $uuid)->firstOrFail();
    
            $modelClass = match ($request->type) {
                'credit' => BillingCredit::class,
                'debit' => BillingDebit::class,
                'payment' => BillingPayment::class,
            };

            $transactionData = [
                'organization_id' => $organization->id,
                'amount' => $request->amount,
            ];
            
            if (in_array($type, ['credit', 'debit'])) {
                $entryData['description'] = $request->description;
            }
            
            if ($type === 'payment') {
                $entryData['processor'] = $request->method;
            }
    
            $entry = $modelClass::create($entryData);
    
            $transaction = BillingTransaction::create([
                'organization_id' => $organization->id,
                'entity_type' => $request->type,
                'entity_id' => $entry->id,
                'description' => $request->type === 'payment' ? $request->method . ' Transaction' : $request->description,
                'amount' => $request->amount,
                'created_by' => auth()->user()->id
            ]);
    
            return $transaction;
        });
    }

   public function destroy($uuid)
    {
        $organization = Organization::where('uuid', $uuid)->first();

        if ($organization) {
            // Delete all teams associated with the organization
            Team::where('organization_id', $organization->id)->delete();
            
            // Delete the organization
            $organization->delete();

            // Return true to indicate successful deletion
            return true;
        }

        return false;
    }

}