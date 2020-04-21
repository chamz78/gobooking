<?php
namespace Modules\Space\Controllers;

use Modules\FrontendController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Space\Models\Space;
use Modules\Location\Models\Location;
use Modules\Core\Models\Attributes;
use Modules\Booking\Models\Booking;
use Modules\Space\Models\SpaceTerm;
use Modules\Space\Models\SpaceTranslation;

class ManageSpaceController extends FrontendController
{
    protected $spaceClass;
    protected $spaceTranslationClass;
    protected $spaceTermClass;
    protected $attributesClass;
    protected $locationClass;
    protected $bookingClass;
    public function __construct()
    {
        parent::__construct();
        $this->spaceClass = Space::class;
        $this->spaceTranslationClass = SpaceTranslation::class;
        $this->spaceTermClass = SpaceTerm::class;
        $this->attributesClass = Attributes::class;
        $this->locationClass = Location::class;
        $this->bookingClass = Booking::class;
    }
    public function callAction($method, $parameters)
    {
        if(!Space::isEnable())
        {
            return redirect('/');
        }
        return parent::callAction($method, $parameters); // TODO: Change the autogenerated stub
    }

    public function manageSpace(Request $request)
    {
        $this->checkPermission('space_view');
        $user_id = Auth::id();
        $list_tour = $this->spaceClass::where("create_user", $user_id)->orderBy('id', 'desc');
        $data = [
            'rows' => $list_tour->paginate(5),
            'breadcrumbs'        => [
                [
                    'name' => __('Manage Spaces'),
                    'url'  => route('space.vendor.index')
                ],
                [
                    'name'  => __('All'),
                    'class' => 'active'
                ],
            ],
            'page_title'         => __("Manage Spaces"),
        ];
        return view('Space::frontend.manageSpace.index', $data);
    }

    public function createSpace(Request $request)
    {
        $this->checkPermission('tour_create');
        $row = new $this->spaceClass();
        $data = [
            'row'           => $row,
            'translation' => new $this->spaceTranslationClass(),
            'space_location' => $this->locationClass::get()->toTree(),
            'attributes'    => $this->attributesClass::where('service', 'space')->get(),
            'breadcrumbs'        => [
                [
                    'name' => __('Manage Spaces'),
                    'url'  => route('space.vendor.index')
                ],
                [
                    'name'  => __('Create'),
                    'class' => 'active'
                ],
            ],
            'page_title'         => __("Create Spaces"),
        ];
        return view('Space::frontend.manageSpace.detail', $data);
    }


    public function store( Request $request, $id ){

        if($id>0){
            $this->checkPermission('space_update');
            $row = $this->spaceClass::find($id);
            if (empty($row)) {
                return redirect(route('space.vendor.index'));
            }

            if($row->create_user != Auth::id() and !$this->hasPermission('space_manage_others'))
            {
                return redirect(route('space.vendor.index'));
            }
        }else{
            $this->checkPermission('space_create');
            $row = new $this->spaceClass();
            $row->status = "publish";
            if(setting_item("space_vendor_create_service_must_approved_by_admin", 0)){
                $row->status = "pending";
            }
        }
        $dataKeys = [
            'title',
            'content',
            'price',
            'is_instant',
            'video',
            'faqs',
            'image_id',
            'banner_image_id',
            'gallery',
            'bed',
            'bathroom',
            'square',
            'location_id',
            'address',
            'map_lat',
            'map_lng',
            'map_zoom',
            'default_state',
            'price',
            'sale_price',
            'max_guests',
            'enable_extra_price',
            'extra_price',
            'is_featured',
            'default_state'
        ];
        if($this->hasPermission('space_manage_others')){
            $dataKeys[] = 'create_user';
        }

        $row->fillByAttr($dataKeys,$request->input());
	    $row->ical_import_url  = $request->ical_import_url;

        $res = $row->saveOriginOrTranslation($request->input('lang'),true);

        if ($res) {
            if(!$request->input('lang') or is_default_lang($request->input('lang'))) {
                $this->saveTerms($row, $request);
            }

            if($id > 0 ){
                return back()->with('success',  __('Space updated') );
            }else{
                return redirect(route('space.vendor.edit',['id'=>$row->id]))->with('success', __('Space created') );
            }
        }
    }

    public function saveTerms($row, $request)
    {
        if (empty($request->input('terms'))) {
            $this->spaceTermClass::where('target_id', $row->id)->delete();
        } else {
            $term_ids = $request->input('terms');
            foreach ($term_ids as $term_id) {
                $this->spaceTermClass::firstOrCreate([
                    'term_id' => $term_id,
                    'target_id' => $row->id
                ]);
            }
            $this->spaceTermClass::where('target_id', $row->id)->whereNotIn('term_id', $term_ids)->delete();
        }
    }

    public function editSpace(Request $request, $id)
    {
        $this->checkPermission('space_update');
        $user_id = Auth::id();
        $row = $this->spaceClass::where("create_user", $user_id);
        $row = $row->find($id);
        if (empty($row)) {
            return redirect(route('space.vendor.index'))->with('warning', __('Space not found!'));
        }
        $translation = $row->translateOrOrigin($request->query('lang'));
        $data = [
            'translation'    => $translation,
            'row'           => $row,
            'space_location' => $this->locationClass::get()->toTree(),
            'attributes'    => $this->attributesClass::where('service', 'space')->get(),
            "selected_terms" => $row->terms->pluck('term_id'),
            'breadcrumbs'        => [
                [
                    'name' => __('Manage Spaces'),
                    'url'  => route('space.vendor.index')
                ],
                [
                    'name'  => __('Edit'),
                    'class' => 'active'
                ],
            ],
            'page_title'         => __("Edit Spaces"),
        ];
        return view('Space::frontend.manageSpace.detail', $data);
    }

    public function deleteSpace($id)
    {
        $this->checkPermission('space_delete');
        $user_id = Auth::id();
        $query = $this->spaceClass::where("create_user", $user_id)->where("id", $id)->first();
        if(!empty($query)){
            $query->delete();
        }
        return redirect(route('space.vendor.index'))->with('success', __('Delete space success!'));
    }

    public function bulkEditSpace($id , Request $request){
        $this->checkPermission('space_update');
        $action = $request->input('action');
        $user_id = Auth::id();
        $query = $this->spaceClass::where("create_user", $user_id)->where("id", $id)->first();
        if (empty($id)) {
            return redirect()->back()->with('error', __('No item!'));
        }
        if (empty($action)) {
            return redirect()->back()->with('error', __('Please select an action!'));
        }
        if(empty($query)){
            return redirect()->back()->with('error', __('Not Found'));
        }
        switch ($action){
            case "make-hide":
                $query->status = "draft";
                break;
            case "make-publish":
                $query->status = "publish";
                break;
        }
        $query->save();
        return redirect()->back()->with('success', __('Update success!'));
    }

    public function bookingReport(Request $request)
    {
        $data = [
            'bookings' => $this->bookingClass::getBookingHistory($request->input('status'), false , Auth::id() , 'space'),
            'statues'  => config('booking.statuses'),
            'breadcrumbs'        => [
                [
                    'name' => __('Manage Space'),
                    'url'  => route('space.vendor.index')
                ],
                [
                    'name' => __('Booking Report'),
                    'class'  => 'active'
                ]
            ],
            'page_title'         => __("Booking Report"),
        ];
        return view('Space::frontend.manageSpace.bookingReport', $data);
    }

    public function bookingReportBulkEdit($booking_id , Request $request){
        $status = $request->input('status');
        if (!empty(setting_item("space_allow_vendor_can_change_their_booking_status")) and !empty($status) and !empty($booking_id)) {
            $query = $this->bookingClass::where("id", $booking_id);
            $query->where("vendor_id", Auth::id());
            $item = $query->first();
            if(!empty($item)){
                $item->status = $status;
                $item->save();
                $item->sendStatusUpdatedEmails();
                return redirect()->back()->with('success', __('Update success'));
            }
            return redirect()->back()->with('error', __('Booking not found!'));
        }
        return redirect()->back()->with('error', __('Update fail!'));
    }

	public function cloneSpace(Request $request,$id){
		$this->checkPermission('space_update');
		$user_id = Auth::id();
		$row = $this->spaceClass::where("create_user", $user_id);
		$row = $row->find($id);
		if (empty($row)) {
			return redirect(route('space.vendor.index'))->with('warning', __('Space not found!'));
		}
		try{
			$clone = $row->replicate();
			$clone->status  = 'draft';
			$clone->push();
			if(!empty($row->terms)){
				foreach ($row->terms as $term){
					$e= $term->replicate();
					if($e->push()){
						$clone->terms()->save($e);

					}
				}
			}
			if(!empty($row->meta)){
				$e= $row->meta->replicate();
				if($e->push()){
					$clone->meta()->save($e);

				}
			}
			if(!empty($row->translations)){
				foreach ($row->translations as $translation){
					$e = $translation->replicate();
					$e->origin_id = $clone->id;
					if($e->push()){
						$clone->translations()->save($e);
					}
				}
			}

			return redirect()->back()->with('success',__('Space clone was successful'));
		}catch (\Exception $exception){
			$clone->delete();
			return redirect()->back()->with('warning',__($exception->getMessage()));
		}
	}

}
