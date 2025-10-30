<?php

namespace App\Http\Controllers\User;

use DB;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreUserOrganization;
use App\Models\Organization;
use App\Models\Team;
use App\Services\OrganizationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrganizationController extends BaseController
{
    private $organizationService;

    /** 
     * OrganizationController constructor.
     *
     * @param UserService $organizationService
     */
    public function __construct()
    {
        $this->organizationService = new OrganizationService();
    }
    
    public function index(){
        $data['organizations'] = Team::with('organization')->where('user_id', auth()->user()->id)->get();
        
        return Inertia::render('User/OrganizationSelect', $data);
    }
    public function show(Request $request, $uuid = null, $mode = null)
    {
        $userId = auth()->id();

        $organization = Organization::where('created_by', $userId)->get();
        return Inertia::render('User/Organization/Show', [
            'title' => __('My Organizations'),
            'organization' => $organization,
            'filters' => $request->all(),
        ]);
    }


    public function selectOrganization(Request $request){
        $organization = Organization::where('uuid', $request->uuid)->first();

        if($organization){
            session()->put('current_organization', $organization->id);
        }

        return to_route('dashboard');
    }

    public function store(StoreUserOrganization $request)
    {
        $user = $request->user();
        if ($user->hasReachedOrganizationLimit()) {
            return redirect()->back()->with('status', [
                'type' => 'error',
                'message' => __('لقد وصلت إلى الحد الأقصى لعدد الشركات المسموح بها.'),
            ]);
        }
        $organization = $this->organizationService->store($request);

        //Count the number of organizations the user has created
        $user->used_organizations += 1;
        $user->save();

        if($organization){
            session()->put('current_organization', $organization->id);

            return to_route('dashboard');
        }
    }

    public function destroy($uuid)
    {
        $query = $this->organizationService->destroy($uuid);

        return redirect('/select-organization')->with(
            'status', [
                'type' => $query ? 'success' : 'error', 
                'message' => $query ? __('Organization deleted successfully!') : __('This organization does not exist!')
            ]
        );
    }
     public function update(Request $request, $uuid)
    {
        $organization = Organization::where('uuid', $uuid)->firstOrFail();

        $organization->update([
            'name' => $request->input('name'),
        ]);


        return redirect('organization/show')->with(
            'status', [
                'type' => 'success', 
                'message' => __('Organization updated successfully!')
            ]
        );
    }
}