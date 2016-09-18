<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Hash;

use App\Http\Requests;

use Response;

use App\Models\Plans;

use App\Models\Customer_info;

use App\Models\Settings;

use App\Models\Customer_locations;

use App\Models\Devices;

use App\Helpers\Helper;

use App\Helpers\Billing;

use App\Helpers\Radius;

use App\User;

class customercontroller extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role');
    }
  
  public function addlocation()
    {
        $total = Customer_info::whereNotNull('billing_id')->count();
        $customers = Customer_info::whereNotNull('billing_id')->get();
        
        $geoservice = Settings::where('setting_name', 'geocoder service')->first();
        $geoservice = $geoservice['setting_value'];
        
        $mapsettings = Helper::buildmapsettings();
        
        return view('customer.addlocation', compact('customers','total','geoservice','mapsettings'));
    }
    
    public function addplan()
    {
        $customers = Customer_info::whereNotNull('billing_id')->has('locations')->get();
        
        $plans = Plans::with('attributes')->get();
        
        $planattributevalues = array();
        
        foreach($plans as $plan){
        
            $attributes = $plan->attributes;
            
            $attributevalues = array(
                "downrate" => "Disabled For This Plan",
                "uprate" => "Disabled For This Plan",
                "downcap" => "Disabled For This Plan",
                "upcap" => "Disabled For This Plan",
                );
        
            foreach($attributes as $attribute){
                
                
                if($attribute['attribute_name'] == 'Download Rate in Mbps'){
                    
                    $downrate = $attribute['attribute_value'];
                    
                }elseif($attribute['attribute_name'] == 'Upload Rate in Mbps'){
                    
                    $uprate = $attribute['attribute_value'];
                    
                }elseif($attribute['attribute_name'] == 'Download Data Cap in GB'){
                    
                    $downcap = $attribute['attribute_value'];
                    
                }elseif($attribute['attribute_name'] == 'Upload Data Cap in GB'){
                    
                    $upcap = $attribute['attribute_value'];
                    
                }
            
            }
            
            $attributevalues['upcap'] = $upcap;
            $attributevalues['downcap'] = $downcap;
            $attributevalues['downrate'] = $downrate;
            $attributevalues['uprate'] = $uprate;
            
            $planattributevalues[$plan->id] = $attributevalues;
            
            $upcap = $downcap = $uprate = $downrate = 'Disabled For This Plan';
            
        }
        
        $verifypin = Settings::where('setting_name', 'Customer PIN')->first();
        $verifypin = $verifypin['setting_value'];
        
        return view('customer.addplan', compact('customers','verifypin','plans','planattributevalues'));
    }
    
    public function displaylocations($id,$mode)
    {
        
      $data =  Customer_info::findorfail($id);
      
      $locdata = array();
      
        foreach($data->locations as $location){
          
          $locationid = $location->id;
          
          $results['id'] = "<input type='radio' name='locid' value='$locationid'>";
          
          $results['add'] = $location->add;
          
          $results['city'] = $location->city;
          
          $results['state'] = $location->state;
          
          $results['zip'] = $location->zip;
          
          if(is_null($location->status)){
            
            $results['status'] = 'Unactive';

         }elseif($location->status == 0){
              
              $results['status'] = 'Suspended';
              
        }elseif($location->status == 1){
              
              $results['status'] = 'Active';
              
          }
          
          
         $results['created_at'] = $location->created_at->toDateString();
          
          $results['updated_at'] = $location->updated_at->toDateString();
          
          if($mode == "radius"){
            
            foreach($location->plans as $plan){
              
              if($plan->pivot->mode == "Radius"){
                
                array_push($locdata, $results);
                
                break;
                
              }
            
            }
            
          }elseif($mode == "activate" or $mode == "device"){
          
           array_push($locdata, $results);
           
          }else{
            
            abort(400);
            
          }
          
        }

         return response()->json($locdata);
        
    }
    
    public function storeaddplan(Request $request)
    {
      
      $verifypin = Settings::where('setting_name', 'Customer PIN')->first();
      $verifypin = $verifypin['setting_value'];
        
        if($verifypin == true){
          $pin = 'required';
        }
        
         $this->validate($request, [
        'id' => 'required|numeric',
        'locid' => 'required|numeric',
        'planid' => 'required|numeric',
        'Mode' => 'required|in:Radius',
        'pin' => $pin,
        ]);
        
        $customer = Customer_info::findorfail($request['id']);
        
        if (Hash::check($request['pin'], $customer->pin)) {
    
        }else{
          
        return redirect('/activatecustomerlocation')->withErrors('PIN is not Valid')->withInput();
          
        }
      
        if($request['Mode'] == 'Radius'){
          
          if(Radius::createuserwithplan($request['id'],$request['planid'],$request['locid'])){
            // Nothing 
          }else{
            
            abort(500, 'Radius Error');
            
          }
          
        }else{
          
          abort(500, 'Unknown Error');
          
        }
        
        Billing::subscribeusertoplan($request['id'],$request['planid']);
      
        $customerloc = Customer_locations::findorfail($request['locid']);
      
        $customerloc->plans()->attach([$request['planid'] => ['mode'=>$request['Mode']]]);
      
        $customerloc->status = 1;
      
        $customerloc->save();
        
        return redirect("/");
    }
    
    public function viewcredentials()
    {
        $customers = Customer_info::whereNotNull('billing_id')->has('locations')->get();
        
        $verifypin = Settings::where('setting_name', 'Customer PIN')->first();
        $verifypin = $verifypin['setting_value'];
        
        return view('customer.viewcredentials', compact('customers','verifypin'));
    }
    
    public function getcredentials(Request $request)
    {
      
      $verifypin = Settings::where('setting_name', 'Customer PIN')->first();
      $verifypin = $verifypin['setting_value'];
        
        if($verifypin == true){
          $pin = 'required';
        }
        
         $this->validate($request, [
        'id' => 'required|numeric',
        'locid' => 'required|numeric',
        'pin' => $pin,
        ]);
        
        $customer = Customer_info::findorfail($request['id']);
        
        if (Hash::check($request['pin'], $customer->pin)) {
    
        }else{
          
        return redirect('/viewcredentials')->withErrors('PIN is not Valid')->withInput();
          
        }
        
        $location = Customer_locations::where('id', $request['locid'])->with(['plans' => function ($query) {
          
          $query->where('mode', 'Radius');

        }])->first();
        
        $results = array();
        
        foreach($location->plans as $plan){
          
          $credential = Radius::getcredentials($request['id'],$plan->id,$request['locid']);
          
          if($credential == false){
            
            abort(500, 'Unexpected Error Retrieving Credentials from Radius Database');
            
          }

          $plan = Plans::findorfail($plan->id); // For some reason I could not just call $plan->name I had to load the plan this way
          
          $credential['name'] = $plan->name;
          
          array_push($results, $credential);
        }
      
         return view('customer.showcredentials', compact('results'));
    }
    
     public function adddevicelocation()
    {
        $customers = Customer_info::whereNotNull('billing_id')->has('locations')->get();
        
        $devices = Devices::has('customer_location', '<', 1)->get();
        
        $verifypin = Settings::where('setting_name', 'Customer PIN')->first();
        $verifypin = $verifypin['setting_value'];
        
        return view('customer.adddevicelocation', compact('customers','verifypin','devices'));
    }
    
    public function storeadddevicelocation(Request $request)
    {
        
      $verifypin = Settings::where('setting_name', 'Customer PIN')->first();
      $verifypin = $verifypin['setting_value'];
        
        if($verifypin == true){
          $pin = 'required';
        }
        
         $this->validate($request, [
        'id' => 'required|numeric',
        'locid' => 'required|numeric',
        'deviceid' => 'required|numeric',
        'pin' => $pin,
        ]);
        
        $customer = Customer_info::findorfail($request['id']);
        
        if (Hash::check($request['pin'], $customer->pin)) {
    
        }else{
          
        return redirect('/adddevicecustomerlocation')->withErrors('PIN is not Valid')->withInput();
          
        }
        
        $device = Devices::findorfail($request['deviceid']);
        
        $device->customer_location_id = $request['locid'];
        
        $device->save();
        
        return redirect("/");
    }
}
