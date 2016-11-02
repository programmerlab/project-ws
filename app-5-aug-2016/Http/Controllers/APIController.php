<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Log\Writer;
use Monolog\Logger as Monolog;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests; 
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\DecryptException;
use App\Helpers\Helper as Helper;
use App\CorporateProfile;
use App\InterviewRating;
use App\Criteria;
use App\Interview;
use App\User;
use App\Position;
use Config;
use View;
use Redirect; 
use Validator;
use Response;
use Auth;
use Crypt;
use Cookie;
use Hash;
use Lang;
use JWTAuth;
use Input;
use Closure;
use URL; 
use App\RatingFeedback;
use App\InviteUser;


class APIController extends Controller
{
	
   /* @method : validateUser
	* @param : email,password,firstName,lastName
	* Response : json
	* Return : token and user details
	* Author : kundan Roy
	* Calling Method : get	
    */

    public function __construct(Request $request) {

       if ($request->header('Content-Type') != "application/json")  {
            $request->headers->set('Content-Type', 'application/json');
        } 
    } 
    
    public function validateUser(Request $request,User $user){

        $input['first_name']    = $request->get('firstName');
        $input['last_name']     = $request->get('lastName'); 
        $input['email']         = $request->get('email'); 
        $input['password']      = Hash::make($request->get('password'));
        $input['deviceID']      = ($request->get('deviceID'))?$request->get('deviceID'):'';
         //Server side valiation
        if($request->get('userID')){
            $validator = Validator::make($request->all(), [
                  
            ]); 
        } 
        else{
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|unique:t_user' 
            ]); 
        }
       
        // Return Error Message
        if ($validator->fails()) {
                    $error_msg  =   [];
            foreach ( $validator->messages()->all() as $key => $value) {
                        array_push($error_msg, $value);     
                    }
                            
            return Response::json(array(
                'status' => 0,
                'message' => $error_msg[0],
                'data'  =>  ''
                )
            );
        }

        $helper = new Helper;
        $group_name =  $helper->getCorporateGroupName($input['email']);
        $email_allow = array('gmail','yahoo','ymail','aol','hotmail');

        if(in_array($group_name, $email_allow))
        {
           return Response::json(array(
                'status' => 0,
                'message' => 'Only corporate email is allowed!',
                'data'  =>  ''
                )
            ); 
        }

        return response()->json(
                            [ 
                                "status"=>1,
                                "message"=>"User validated successfully.",
                                'data'=>$request->all()
                            ]
                        );  
    }   

   /* @method : register
    * @param : email,password,deviceID,firstName,lastName
    * Response : json
    * Return : token and user details
    * Author : kundan Roy
    * Calling Method : get  
    */

    public function register(Request $request,User $user)
    {   

        $input['first_name'] 	= $request->get('firstName');
    	$input['last_name'] 	= $request->get('lastName'); 
    	$input['email'] 		= $request->get('email'); 
    	$input['password'] 	    = Hash::make($request->get('password'));
    	$input['deviceID'] 	    = ($request->get('deviceID'))?$request->get('deviceID'):'';
        $input['positionID']    = ($request->get('positionName'))?$request->get('positionName'):'';
        
        if($request->get('userID')){
            $u = $this->updateProfile($request,$user);
            return $u;
        } 

        //Server side valiation
        $validator = Validator::make($request->all(), [
           'email' => 'required|email|unique:t_user',
            'positionName' => 'required'
        ]);
        /** Return Error Message **/
        if ($validator->fails()) {
                   	$error_msg	=	[];
	        foreach ( $validator->messages()->all() as $key => $value) {
	        			array_push($error_msg, $value);		
	        		}
	        		 		
          	return Response::json(array(
	          	'status' => 0,
	            'message' => $error_msg[0],
	            'data'	=>	''
	            )
          	);
        }  
        
        $helper = new Helper;
        /** --Create USER-- **/
        $user = User::create($input); 
       // $data = ['userID'=>$user->userID,'name'=>$user['first_name'],'email'=>$user['email'],'firstName'=>$user['first_name'],'lastName'=>$user['last_name']];
         
        /** --Create Company Group-- **/
        $h = $helper->createCompanyGroup($request->get('email'),$user->userID);
        /** --Send Mail after Sign Up-- **/
        $subject = "Welcome to Udex! Verify your email address to get started";
        $email_content = array('receipent_email'=> $request->get('email'),'subject'=>$subject);
        $verification_email = $helper->sendMailFrontEnd($email_content,'verification_link',['name'=> $request->get('firstName')]);
       
        return response()->json(
                            [ 
                                "status"=>1,
                                "message"=>"Thank you for registration. Please verify your email.",
                                'data'=>$request->except('password')
                            ]
                        );
    }

/* @method : update User Profile
    * @param : email,password,deviceID,firstName,lastName
    * Response : json
    * Return : token and user details
    * Author : kundan Roy
    * Calling Method : get  
    */
    public function updateProfile(Request $request,User $user)
    {       
        $user_id    =   $request->get('userID'); 
        if(!Helper::isUserExist($user_id))
        {
            return Response::json(array(
                'status' => 0,
                'message' => 'Invalid user ID!',
                'data'  =>  ''
                )
            );
        } 
        $user       =   User::find($user_id);
        $user->first_name    = ($request->get('firstName'))?$request->get('firstName'):$user->first_name;
        $user->last_name     = ($request->get('lastName'))?$request->get('lastName'):$user->last_name;
        $user->deviceID      = ($request->get('deviceID'))?$request->get('deviceID'):$user->deviceID;
        $user->positionID    = ($request->get('positionName'))?$request->get('positionName'):$user->positionID;
 

        //Server side valiation
        $validator = Validator::make($request->all(), [
            'positionName' => 'required'
        ]);
        /** Return Error Message **/
        if ($validator->fails()) { 
            return Response::json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
                'data'  =>  ''
                )
            );
        } 
 
        // Update USER
        $user->save();  
        $position_name = Helper::getPositionNameById($user->positionID);
         
        $data = ['userID'=>$user->userID,'firstName'=>$user->first_name,'lastName'=>$user->last_name,'email'=>$user->email,'positionID'=> $user->positionID,'positionName'=>$position_name ];
       
        return response()->json(
                            [ 
                                "status"=>1,
                                "message"=>"Profile updated successfully.",
                                'data'=>$data
                            ]
                        );
    }

   /* @method : login
	* @param : email,password and deviceID
	* Response : json
	* Return : token and user details
	* Author : kundan Roy	
    */
    public function login(Request $request)
    {
        $input = $request->all();
    	if (!$token = JWTAuth::attempt(['email'=>$request->get('email'),'password'=>$request->get('password')])) {
            return response()->json([ "status"=>0,"message"=>"Invalid email or password. Try again!" ,'data' => '' ]);
        }
        $user = JWTAuth::toUser($token); 
        $data['deviceToken'] = $token;
        $data['userID'] = $user->userID;
        $data['firstName'] = $user->first_name;
        $data['lastName'] = $user->last_name;
        $data['email'] = $user->email;

        if(!$user->status)
        {
            return response()->json([ "status"=>0,"message"=>"Your email is not verified!" ,'data' => '' ]);   
        }
        $user = User::find($user->userID);
        $user->deviceID  = $request->get('deviceID');
        $user->save();

    	return response()->json([ "status"=>1,"message"=>"Successfully logged in." ,'data' => $data ]);

    } 
   /* @method : get user details
	* @param : Token and deviceID
	* Response : json
	* Return : User details	
   */
   
    public function getUserDetails(Request $request)
    {
    	$user = JWTAuth::toUser($request->get('deviceToken'));
        $data['userID'] = $user->userID;
        $data['firstName'] = $user->first_name;
        $data['lastName'] = $user->last_name;
        $data['email'] = $user->email;
        $data['positionID'] =  $user->positionID;
        $data['positionName'] =  Helper::getPositionNameById($user->positionID);
        return response()->json(
                [ "status"=>1,
                  "code"=>200,
                  "message"=>"Record found successfully." ,
                  "data" => $data 
                ]
            ); 
    }
   /* @method : Email Verification
    * @param : token_id
    * Response : json
    * Return :token and email 
   */
   
    public function emailVerification(Request $request)
    {
        $verification_code = $request->get('verification_code');
        $email    = $request->get('email');

        if (Hash::check($email, $verification_code)) {
           $user = User::where('email',$email)->get()->count();
           if($user>0)
           {
              User::where('email',$email)->update(['status'=>1]);  
           }else{
            echo "Verification link is Invalid or expire!"; exit();
                return response()->json([ "status"=>0,"message"=>"Verification link is Invalid!" ,'data' => '']);
           }
           echo "Email verified successfully."; exit();  
           return response()->json([ "status"=>1,"message"=>"Email verified successfully." ,'data' => '']);
        }else{
            echo "Verification link is Invalid!"; exit();
            return response()->json([ "status"=>0,"message"=>"Verification link is invalid!" ,'data' => '']);
        }
    }
   
   /* @method : logout
    * @param : token
    * Response : "logout message"
    * Return : json response 
   */
    public function logout(Request $request)
    {   
        JWTAuth::invalidate($request->get('deviceToken'));

        return  response()->json([ 
                    "status"=>1,
                    "code"=> 200,
                    "message"=>"You've successfully signed out.",
                    'data' => ""
                    ]
                );
    }
   /* @method : forget password
    * @param : token,email
    * Response : json
    * Return : json response 
    */
    public function forgetPassword(Request $request)
    {   
        $email = $request->get('email');
        //Server side valiation
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        $helper = new Helper;
       
        if ($validator->fails()) {
            $error_msg  =   [];
            foreach ( $validator->messages()->all() as $key => $value) {
                        array_push($error_msg, $value);     
                    }
                            
            return Response::json(array(
                'status' => 0,
                'message' => $error_msg[0],
                'data'  =>  ''
                )
            );
        }

        $user =   User::where('email',$email)->get();

        if($user->count()==0){
            return Response::json(array(
                'status' => 0,
                'message' => "Oh no! The address you provided isn't in our system",
                'data'  =>  ''
                )
            );
        }
        $user_data = User::find($user[0]->userID);
        $temp_password = Hash::make($email);
       
        
      // Send Mail after forget password
        $temp_password =  Hash::make($email);
 
        $email_content = array(
                        'receipent_email'   => $request->get('email'),
                        'subject'           => 'Your Udex Account Password',
                        'name'              => $user[0]->first_name,
                        'temp_password'     => $temp_password,
                        'encrypt_key'       => Crypt::encrypt($email)
                    );
        $helper = new Helper;
        $email_response = $helper->sendMail(
                                $email_content,
                                'forgot_password_link'
                            ); 
       
       return   response()->json(
                    [ 
                        "status"=>1,
                        "code"=> 200,
                        "message"=>"Reset password link has sent. Please check your email.",
                        'data' => ''
                    ]
                );
    }

   /* @method : change password
    * @param : token,oldpassword, newpassword
    * Response : "message"
    * Return : json response 
   */
    public function changePassword(Request $request)
    {   
        $user = JWTAuth::toUser($request->get('deviceToken'));
        $user_id = $user->userID; 
        $old_password = $user->password;
     
        $validator = Validator::make($request->all(), [
            'oldPassword' => 'required',
            'newPassword' => 'required|min:6'
        ]);
        // Return Error Message
        if ($validator->fails()) {
            $error_msg  =   [];
            foreach ( $validator->messages()->all() as $key => $value) {
                        array_push($error_msg, $value);     
                    }
                            
            return Response::json(array(
                'status' => 0,
                'message' => $error_msg[0],
                'data'  =>  ''
                )
            );
        }

         
        if (Hash::check($request->get('oldPassword'),$old_password)) {

           $user_data =  User::find($user_id);
           $user_data->password =  Hash::make($request->get('newPassword'));
           $user_data->save();
           return  response()->json([ 
                    "status"=>1,
                    "code"=> 200,
                    "message"=>"Password changed successfully.",
                    'data' => ""
                    ]
                );
        }else
        {
            return Response::json(array(
                'status' => 0,
                'message' => "Old password mismatch!",
                'data'  =>  ''
                )
            );
        }         
    }
   /* @method : get Postion
    * @param : position name
    * Response : json
    * Return : Postion details 
    */
   
    public function getPosition(Request $request,Position $position)
    {
        $user_email = $request->get('email');   
        $helper = new Helper;
        $corporate_group_name = $helper->getCorporateGroupName($request->get('email'));
        $company_url          = $helper->getCompanyUrl($request->get('email'));
        
        $position = $position->where('email','')
                        ->orWhere('corporate_group_name',$corporate_group_name)
                        ->where('company_url',$company_url)
                        ->get([
                            'id as positionId',
                            'position_name as positionName'
                        ]);

       if($position->count()>0){
            return  response()->json([ 
                        "status"=>1,
                         "code"=> 200,
                        "message"=>"Record found successfully." ,
                        'data' => $position
                        ]
                    );
       }else {
            return  response()->json([ 
                        "status"=>0,
                        "code"=> 404,
                        "message"=>"Record not found!" ,
                        'data' => ""
                        ]
                    );
       }
    }
   /* @method : Add Postion
    * @param : position name
    * Response : json
    * Return : Postion details 
   */
    public function addPosition(Request $request,Position $position)
    {
       
        if(empty($request->get('positionName'))){
            return  response()->json([ 
                        "status"=>0, 
                        "message"=>"Position name is required!",
                        'data' => ""
                        ]
                    );
        }
        if(empty($request->get('email'))){
            return  response()->json([ 
                        "status"=>0, 
                        "message"=>"Email is required!",
                        'data' => ""
                        ]
                    );
        }
        $helper = new Helper; 
        $corporate_group_name = $helper->getCorporateGroupName($request->get('email'));
        $company_url          = $helper->getCompanyUrl($request->get('email'));

        $duplicate_position = Position::where('corporate_group_name',$corporate_group_name)
                                    ->where('position_name',$request->get('positionName'))
                                    ->get();
        
        if($duplicate_position->count()>0)
        {
            return  response()->json([ 
                        "status"=>0,
                        "code"=> 404,
                        "message"=>"Position name already exist!",
                        'data' => ""
                        ]
                    );
        }    
        $input = $request->all();
        $position->position_name = $request->get('positionName');
        //$position->userID = empty($request->get('userID'))?$request->get('userID'):'';
        $position->corporate_group_name = $corporate_group_name;
        $position->email = $request->get('email');
        $position->company_url = $company_url;
        $position->save();

        $data = [
                'positionName'=>$position->position_name,
                'email'=>$request->get('email'),
                'positionId' => $position->id
                ];
        return  response()->json([ 
                    "status"=>1,
                    "code"=> 200,
                    "message"=>"Position added successfully.",
                    'data' => $data
                    ]
                );
       
    }
   /* @method : get Criteria
    * @param : position name
    * Response : json
    * Return : Postion details 
    * Class : Helper@getUserGroupedID,param@user_id
    */
   
    public function getCriteria(Request $request,Criteria $criteria)
    {
        $user_id = $request->get('userID');   
        $existing_user_from_company = [];
        // get all existing_user who added criteria from same company
        if(Helper::isUserExist($user_id))
        {
           $existing_user_from_company = Helper::getUserGroupedID($user_id);   
        }    
     
        // ($existing_user_from_company);
        $criteria = $criteria->whereIn('created_by',$existing_user_from_company)
                        ->orWhere('created_by',0)
                        ->orWhere('created_by',$user_id)
                        ->groupBy('interview_criteria','ASC')
                        ->get([
                            'id as criteriaID',
                            'interview_criteria as criteriaText'
                        ]); 

       if($criteria->count()>0){
            return  response()->json([ 
                        "status"=>1,
                         "code"=> 200,
                        "message"=>"Record found successfully." ,
                        'data' => $criteria
                        ]
                    );
       }else {
            return  response()->json([ 
                        "status"=>0,
                        "code"=> 404,
                        "message"=>"Record not found!" ,
                        'data' => ""
                        ]
                    );
       }
    }

/* @method : Add Postion
    * @param : position name
    * Response : json
    * Return : Postion details 
    */
    public function addCriteria(Request $request,Criteria $criteria)
    {
        $user_id    =   $request->get('userID');
        if(empty($request->get('criteriaText'))){
            return  response()->json([ 
                        "status"=>0, 
                        "message"=>"Criteria text is required!",
                        'data' => ""
                        ]
                    );
        }
        if(empty($request->get('userID'))){
            return  response()->json([ 
                        "status"=>0,
                        "message"=>"User id is required!",
                        'data' => ""
                        ]
                    );
        }
        $existing_user_from_company = [];
        // get all existing_user who added criteria from same company
        if(Helper::isUserExist($user_id))
        {
           $existing_user_from_company = Helper::getUserGroupedID($user_id);   
        }

        $duplicate_criteria = Criteria::where('interview_criteria',$request->get('criteriaText'))
                                    ->where('created_by',0) 
                                    ->orwhere('interview_criteria',$request->get('criteriaText'))
                                    ->whereIn('created_by',$existing_user_from_company)
                                    ->get();
        
        if($duplicate_criteria->count()>0)
        {
            return  response()->json([ 
                        "status"=>0,
                        "message"=>"This criteria already exist!",
                        'data' => ""
                        ]
                    );
        }    
        $input = $request->all();
        $criteria->interview_criteria = ucfirst($request->get('criteriaText'));
        $criteria->created_by = !empty($user_id )?$user_id :'';
        $criteria->save(); 
        $data = [
                    'criteriaText'=>$criteria->interview_criteria,
                    'userID'=>$request->get('userID'),
                    'criteriaID'=>$criteria->id
                ];

        return  response()->json([ 
                    "status"=>1,
                    "code"=> 200,
                    "message"=>"Criteria added successfully.",
                    'data' => $data
                    ]
                );
       
    }

   /* @method : get Interviewer
    * @param : email
    * Response : json
    * Return : Interview details 
    */
   
    public function getInterviewer(Request $request)
    {
        $user_id = $request->get('userID');   
        $helper = new Helper;
        // Create Company Group

        $corp_profile       = CorporateProfile::where('userID',$request->get('userID'))->get();
        $corp_profile_name  = $corp_profile->lists('company_url','userID');

        if($corp_profile->count()==0)
        {
            return  response()->json([ 
                    "status"=>0,
                    "code"=> 404,
                    "message"=>"Record not found!",
                    'data' => ""
                    ]
                ); 
        }
        $user_from_same_company = CorporateProfile::where('company_url',$corp_profile_name[$user_id])->get();
        
        $get_interviewer_list = User::whereIn('userID', $user_from_same_company->lists('userID'))
                                     ->where('status',1)->get();
         
        $data = [];
        foreach ($get_interviewer_list  as $key => $result) {
            $position_data =  Position::find($result->positionID);
            $data[] = [ 
                        'interviewerID'   => $result->userID,
                        'interviewerText' => $result->first_name.' '.$result->last_name,
                        'positionName'    => $position_data->position_name 
                      ];
        }
      
       if($get_interviewer_list->count())
       {
           return  response()->json([ 
                    "status"=>1,
                    "code"=> 200,
                    "message"=>"Record found successfully.",
                    'data' => $data
                    ]
                ); 
        }else {
            return  response()->json([ 
                    "status"=>0,
                    "code"=> 404,
                    "message"=>"Record not found!",
                    'data' => ""
                    ]
                );
        }
       
    }
   /* @method : get Interviewer
    * @param : email
    * Response : json
    * Return : Postion details 
    */
    public function addInterview(Request $request,Interview $interview)
    {
       $user_id =  $request->get('userID');
       $user_token_data = JWTAuth::toUser($request->get('deviceToken'));
       $user_id =  isset($user_id)?$user_id:$user_token_data->userID; 

        //Server side valiation
        $validator = Validator::make($request->all(), [
            'condidateName'     => 'required',
            'shortDescription' => 'required'
        ]);
        // Return Error Message
        if ($validator->fails()) {
                    $error_msg  =   [];
            foreach ( $validator->messages()->all() as $key => $value) {
                        array_push($error_msg, $value);     
                    }
                            
            return Response::json(array(
                'status' => 0,
                'message' => $error_msg[0],
                'data'  =>  ''
                )
            );
        }
        $input_data = $request->all();
        $criteriaID = isset($input_data['criteria'][0]['criteriaID'])
                        ?
                        $input_data['criteria'][0]['criteriaID']
                        :
                        0;
                        
        $interviewerID = isset($input_data['interviewer'][0]['interviewerID'])
                            ?
                            $input_data['interviewer'][0]['interviewerID']
                            :
                            0;
        if($criteriaID==0){
            return  response()->json([ 
                    "status"    =>  0,
                    "message"   =>  "Criteria field is required.",
                    'data'      =>  ""
                   ]
                );
        }
        if($interviewerID==0){
            return  response()->json([ 
                    "status"    =>  0,
                    "message"   =>  "Interviewer field is required.",
                    'data'      =>  ""
                   ]
                );
        }
        $criteria_id = '';
        $interviewer_id = '';
        foreach ($input_data['criteria'] as $key => $cid) {
            $criteria_id = $criteria_id.','.$cid['criteriaID'];
        }
        foreach ($input_data['interviewer'] as $key => $int_id) {
            $interviewer_id = $interviewer_id.','.$int_id['interviewerID'] ;
        }  
         
        $interview->condidate_name      = $request->get('condidateName');
        $interview->short_description   = $request->get('shortDescription');
        $interview->interviewerID       = ltrim($interviewer_id,',');
        $interview->criteriaID          = ltrim($criteria_id,',');
        $interview->interview_create_by = $request->get('userID');
        $interview->interview_date      = $request->get('interviewDate');
        $interview->save();
        // SEnd meail after create Interview
        $interviewerID = ltrim($interviewer_id,',');
        $extract_interviewer_id = str_getcsv($interviewerID);
       
        foreach ($extract_interviewer_id as $key => $mail_to_interviewer) {
            $sender_name   =  User::find($user_id);

            $user_data     = User::find($mail_to_interviewer);
            $user_email    = $user_data->email;
            $user_name     = $user_data->first_name;
            $condidate_name = $request->get('condidateName');
            $subject       = ucfirst($sender_name->first_name)." has added you to a Udex Interview Evaluation";   
            $email_content = array('receipent_email'=> $user_email,'subject'=>$subject,'name'=>$user_name,'interview_created_by'=>$sender_name->first_name.' '.$sender_name->last_name,'condidate_name'=>ucwords($condidate_name));
            $helper = new Helper;
            $interviewer_notify_mail = $helper->sendNotificationMail($email_content,'interviewer_notify_mail',['name'=> $user_name]);
        }

        if($interview->save())
        {
            return  response()->json([ 
                    "status"=>1,
                    "code"=> 200,
                    "message"=>"Interview added successfully.",
                    'data' => $request->all()
               ]
            ); 
        }
    }
   /* @method : Get Directory
    * @param : Interviewer ID
    * Response : json
    * Return :  
    */
    public function getDirectory(Request $request,Interview $interview)
    {
        $user_auth = JWTAuth::toUser($request->get('deviceToken'));

        $uid = isset($user_auth->userID)?$user_auth->userID:'';
        $user_id = ($request->get('userID'))?$request->get('userID'):$uid;

        $is_user_exist = Helper::isUserExist($user_id);
        $search = $request->get('search');
        
        if(!$is_user_exist){
            return  response()->json([ 
                    "status"    =>  0,
                    "message"   =>  "Record not found",
                    'data'      =>  []
                   ]
                );
        }

        $corp_profile       = CorporateProfile::where('userID',$user_id)->get();
        $corp_profile_name  = $corp_profile->lists('company_url','userID');
        $user_from_same_company = CorporateProfile::where('company_url',$corp_profile_name[$user_id])->get();
       
        $get_interviewer_list = User::whereIn('userID', $user_from_same_company->lists('userID'))
                                     ->where('status',1)->get();
          
       // $condidate =  Interview::whereRaw('FIND_IN_SET('.$user_id.',interviewerID)')->get();
        $query =  Interview::query();
        $query->whereRaw('FIND_IN_SET('.$user_id.',interviewerID)');
        $condidate =  $query->get();
       
        if($condidate->count()==0)
        {
           $r =   response()->json([ 
                    "status"    =>  0,
                    "message"   =>  "Directory data not found",
                    'data'      =>  []
                   ]
                ); 
        } 
        $my_data = [];
        foreach ($condidate as $key => $condidate_record) {
            
            // /array_intersect
            $login_user_id = str_getcsv($condidate_record->interviewerID);
            $int_list = $get_interviewer_list->lists('userID')->toArray();

            if(count(array_intersect($login_user_id,$int_list))>0) {

                $interviewer_data = Helper::getInterviewerFromInterviewDirectory($condidate_record->interviewerID);
                $rating = Helper::getRatingByCondidateID($condidate_record->id);
                // Rating pending if not rated by all user
                $is_evaluate = InterviewRating::where('condidateID',$condidate_record->id)->count();   
                $interviewer_count =  count($interviewer_data);
                $condidate_count_evaluation = $is_evaluate;
                $is_future     = \Carbon\Carbon::parse($condidate_record->interview_date)->isFuture();
                $is_past       = \Carbon\Carbon::parse($condidate_record->interview_date)->isPast();
                

                if($is_future===true){
                    $rs = "Upcoming";
                }elseif ($is_past===true) {
                     $rs = "pending";
                }else{
                    $rs = "pending"; 
                }

              
                    $my_data[] =   [
                                'condidateName' =>  $condidate_record->condidate_name,
                                'condidateID' =>  $condidate_record->id,
                                'shortDescription' => $condidate_record->short_description,
                                'comment'   =>    $condidate_record->comment,
                                'rating'    => Helper::getRatingByCondidateID($condidate_record->id),
                                'ratingStatus' => ($interviewer_count==$condidate_count_evaluation)?'Evaluated':$rs,
                                'interviewer' => $interviewer_data
                            ];
           
            }            
        }
        $temp_data = [];
        $condidate =''; 
        $data= [];
       
        foreach ($user_from_same_company as $key => $user_record) {
            //$condidate =  Interview::whereRaw('FIND_IN_SET('.$user_record->userID.',interviewerID)')->get();
            $query =  Interview::query();
            //$query->whereRaw('FIND_IN_SET('.$user_record->userID.',interviewerID)');
            $condidate =  $query->get(); 

            foreach ($condidate as $key => $condidate_record) {

                $login_user_id = str_getcsv($condidate_record->interviewerID);
                $int_list = $get_interviewer_list->lists('userID')->toArray();

                if(count(array_intersect($login_user_id,$int_list))>0){ 
               
                    $interviewer_all_data = Helper::getInterviewerFromInterviewDirectory($condidate_record->interviewerID);
                    $rating   = Helper::getRatingByCondidateID($condidate_record->id);
                    // Rating pending if not rated by all user
                    $is_evaluate = InterviewRating::where('condidateID',$condidate_record->id)->count();   
                    $interviewer_count =  count($interviewer_all_data);
                    $condidate_count_evaluation = $is_evaluate;
                    $is_future       = \Carbon\Carbon::parse($condidate_record->interview_date)->isFuture();
                    $is_past       = \Carbon\Carbon::parse($condidate_record->interview_date)->isPast();

                    
                    if($is_future===true){
                        $rs = "Upcoming";
                    }elseif ($is_past===true) {
                         $rs = "pending";
                    }else{
                        $rs = "pending"; 
                    } 
                  
                    $temp_data[] =   [
                                    'condidateName' =>  $condidate_record->condidate_name,
                                    'condidateID' =>  $condidate_record->id,
                                    'shortDescription' => $condidate_record->short_description,
                                    'comment'   =>    $condidate_record->comment,
                                    'rating'    => Helper::getRatingByCondidateID($condidate_record->id),
                                    //'ratingStatus' => ($rating>0)?'Evaluated':"Pending",
                                    'ratingStatus' => ($interviewer_count==$condidate_count_evaluation)?'Evaluated':$rs,
                                    'interviewer' => $interviewer_all_data
                               ];
                }                  
            }
        }
       
        $all_data = array();
        foreach ($temp_data as $key => $value) {
                 $all_data[$value['condidateID']] = $value; 
        }
         
        foreach ($all_data as $key => $value) { 
               $data[] =   $value;  
        }        
        
        switch ($search) {
         case 'name':
             $data = $this->array_msort($data, array('condidateName'=>SORT_ASC));
             break;
         case 'date':
              $data = $this->array_msort($data, array('condidateID'=>SORT_DESC));
             break;
         case 'rate':
              $data = $this->array_msort($data, array('rating'=>SORT_DESC));
             break;
        case 'status':
              $data = $this->array_msort($data, array('ratingStatus'=>SORT_DESC));
             break;     
         default:
            //$query->orderBy('id','DESC'); 
             break;
        }

        switch ($search) {
         case 'name':
             $my_data = $this->array_msort($my_data, array('condidateName'=>SORT_ASC));
             break;
         case 'date':
              $my_data = $this->array_msort($my_data, array('condidateID'=>SORT_DESC));
             break;
         case 'rate':
              $my_data = $this->array_msort($my_data, array('rating'=>SORT_DESC));
             break;
        case 'status':
              $my_data = $this->array_msort($my_data, array('ratingStatus'=>SORT_DESC));
             break;     
         default:
             break;
        }

        if($search)
        {
           $data = array_values($data); 
           $my_data = array_values($my_data);
        }
         
        return  response()->json([ 
                    "status"=>1,
                    "code"=> 200,
                    "message"=>"Record found successfully.",
                    'data' => ['all'=>$data,'mine'=>$my_data]
                   ]
                ); 

    }
    public function array_msort($array, $cols)
    {
    $colarr = array();
    foreach ($cols as $col => $order) {
        $colarr[$col] = array();
        foreach ($array as $k => $row) { $colarr[$col]['_'.$k] = strtolower($row[$col]); }
    }
    $eval = 'array_multisort(';
    foreach ($cols as $col => $order) {
        $eval .= '$colarr[\''.$col.'\'],'.$order.',';
    }
    $eval = substr($eval,0,-1).');';
    eval($eval);
    $ret = array();
    foreach ($colarr as $col => $arr) {
        foreach ($arr as $k => $v) {
            $k = substr($k,1);
            if (!isset($ret[$k])) $ret[$k] = $array[$k];
            $ret[$k][$col] = $array[$k][$col];
        }
    }
    return $ret;

}
   /* @method : Get Condidate rating
    * @param : Interviewer ID
    * Response : json
    * Return :   getCondidateRecord
    */
    public function get_condidate_record(Request $request, Interview $interview)
    {   
        $condidate_id   =  $request->get('directoryID');
        $condidate_name =  Helper::getCondidateNameByID($condidate_id);
        if($condidate_name==null){
            return  json_encode(
                        [  
                            "status"=>0,
                            "code"=> 404,
                            "message"=>"Record not found", 
                            'data' => ""
                        ] 
                    );  
        }
        $interview_data     =  InterviewRating::where('condidateID',$condidate_id)->get();
        $interview_details  = [];
        $c_details          = Interview::find($condidate_id);
        $interviewerComment = [];
        $date           = \Carbon\Carbon::parse($c_details->created_at)->format('m/d/Y');
       /* $date_diff      = \Carbon\Carbon::parse('27-07-2016')->diffForHumans();  
        $is_tomorrow    = \Carbon\Carbon::parse('28-07-2016')->isTomorrow();
        $is_today       = \Carbon\Carbon::parse('28-07-2016')->isTomorrow();
       */
        if($interview_data->count()>0){
            $interview_criteriaID =[];
            foreach ($interview_data as $key => $result) {

                $rating_value    = str_getcsv($result->rating_value);
                $interviewerName = Helper::getUserDetails($result->interviewerID);
                
                if( !empty($result->comment))
                {
                  $interviewerComment[]  =[
                            'firstName' => $interviewerName['firstName'],
                            'lastName'  => $interviewerName['lastName'],
                            'comment'   => $result->comment];
                }    
                
                $interview_details[]   =  Helper::getCriteriaById(str_getcsv($result->interview_criteriaID),$rating_value,$interviewerName,$result->comment); 
                 
            }
        }else{ 
             return  response()->json([  
                            "status"=>1,
                            "code"=> 200,
                            "message"=>"Record found successfully.",  
                            "data"  =>  array(
                                "date"=>$date,
                                "details"=>$interview_details,
                                "comment"=>$interviewerComment,
                                ) 
                        ] 
                    );  
        } 
        return  response()->json([ 
                    "status"=>1,
                    "code"=> 200, 
                    "message"=>"Record found successfully.",  
                    "data"  =>  array(
                        "date"=>$date,
                        "details"=>$interview_details,
                        "comment"=>$interviewerComment,
                        )  
                    ]    
                );  

         // "comment" => $comment,
                               // "ratingDetail"=>$interview_details]
    }

    /* @method : Get Condidate rating
    * @param : Interviewer ID
    * Response : json
    * Return :  
    */
     
    public function getRecentCondidateRecord(Request $request,Interview $interview)
    {   
       // $user_id = $request->get('userID'); 
        $user_id =  $request->get('userID');
        $token = $request->get('deviceToken');
        $token_user_id='';
        if($token){
           $user_token_data = JWTAuth::toUser($request->get('deviceToken'));
           $token_user_id  = $user_token_data->userID;
        }
        
        $user_id =  isset($user_id)?$user_id:$token_user_id; 

        if(empty($user_id))
        {
            return  response()->json([ 
                "status"=>0,
                "code"=> 404,
                "message"=>"UserID required!",
                "data"  => ""
               ]
            );
        } 

        $corp_profile       = CorporateProfile::where('userID',$user_id)->get();
        $corp_profile_name  = $corp_profile->lists('company_url','userID');
        if($corp_profile->count()==0)
        {
            return  response()->json([ 
                "status"=>0,
                "code"=> 404,
                "message"=>"Unauthorised access!",
                "data"  => ""
               ]
            );
        } 

        $user_from_same_company = CorporateProfile::where('company_url',$corp_profile_name[$user_id])->get();
        $same_company_id    =   $user_from_same_company->lists('userID');
        $get_interviewer_list = User::whereIn('userID', $user_from_same_company->lists('userID'))
                                     ->where('status',1)->get();
         
        $interview_details = [];
        foreach ($user_from_same_company as $key => $user_record) { 
            $interviewD = Interview::where(function($query) use($user_record){
                $query->whereRaw('FIND_IN_SET('.$user_record->userID.',interviewerID)');
                }
            )
            ->get(); 
            $interview_condidate_data = $interviewD->lists('interviewerID','id'); 
            foreach ($interview_condidate_data as $key => $icd) {   
                $interviewData[$key] = $icd;
            } 
        }    
         ksort($interviewData);
        $interview_details_p=[]; 
        $interview_details_c=[];
        foreach ($interviewData as $cid => $cvalue) {
             
            $interview = Interview::where('id',$cid)->get(); 
            $condidate_detail  =  Helper::getCondidateNameByID($cid);
                 
            foreach ($interview as $key => $condidate) {
                
                $condidate_id   = $condidate->id;
                $int_ids = str_getcsv($condidate->interviewerID);
                $interview_data =  InterviewRating::where('condidateID',$condidate_id )
                                    ->whereIn('interviewerID',$int_ids)
                                    ->get();
                
                //$interview_details_c =[];
                $rate_int_id='';         
                if($interview_data->count()>0)
                {    
                    $interview_criteriaID =[];
                    foreach ($interview_data as $key => $result) {
                        $rate_int_id = $result->interviewerID;
                        $rating_value    = str_getcsv($result->rating_value);
                        $total_criteria = str_getcsv($result->interview_criteriaID);
                        $rating_value_record[]  = number_format(floatval((array_sum($rating_value)/count($total_criteria))),1);
                        
                        $interviewerName = Helper::getUserDetails($result->interviewerID);
                        $interview_details[]   =  Helper::getAllCondidateDetails(str_getcsv($result->interview_criteriaID),$rating_value,$interviewerName,$result->comment); 
                    }
                    
                 }   
                    
                $pending_int_ids = str_getcsv($condidate->interviewerID);
                foreach ($same_company_id as $key => $arrData) {
                       $arr_usedID[] = $arrData;
                }  
                foreach ($pending_int_ids as $key => $pid) {
                    
                    if($pid!=$rate_int_id && (in_array($pid, $arr_usedID)))
                    {
                         if($pid!=null){
                            $interviewerName2 = Helper::getUserDetails($pid);
                            $rating_value    = [];
                            $criteriaIDs = str_getcsv($condidate->criteriaID);
                            $comments = $condidate->comment;
                            $interview_details[]   =  Helper::getAllCondidateDetails($criteriaIDs,$rating_value,$interviewerName2,$comments); 
                        } 
                    } 
                 }
                
            $arr_data[] = ['condidateDetail'=>$condidate_detail,'ratingDetail'=>$interview_details];

            }
             
            $interview_details=[];  
            $rate_int_id =null;    
        }     
       $mine_interview = $this->mineRecentCondidateInterview($user_id,$same_company_id);
        return  response()->json([ 
                    "status"=>1,
                    "code"=> 200,
                    "message"=>"Record found successfully.",
                    "data"  =>  ['allRecentData'=>$arr_data,'mineRecent'=>$mine_interview]
                   ]
                );  
    }
   /* @method : Get recent condidate evaluation rating data
    * @param : Interviewer ID
    * Response : json
    * Return :  json
    */
    public function mineRecentCondidateInterview($interviewerID=null,$same_company_id=null){
            $interviewD = Interview::where(function($query) use($interviewerID){
                $query->whereRaw('FIND_IN_SET('.$interviewerID.',interviewerID)');
                }
            )
            ->get(); 
            $interview_condidate_data = $interviewD->lists('interviewerID','id'); 
            foreach ($interview_condidate_data as $key => $icd) {   
                $interviewData[$key] = $icd;
            } 
        foreach ($interviewData as $cid => $cvalue) {
             
            $interview = Interview::where('id',$cid)->get(); 
            $condidate_detail  =  Helper::getCondidateNameByID($cid);
                 
            foreach ($interview as $key => $condidate) {
                
                $condidate_id   = $condidate->id;
                $int_ids = str_getcsv($condidate->interviewerID);
                $interview_data =  InterviewRating::where('condidateID',$condidate_id )
                                    ->whereIn('interviewerID',$int_ids)
                                    ->get();
                
                //$interview_details_c =[];
                $rate_int_id='';         
                if($interview_data->count()>0)
                {    
                    $interview_criteriaID =[];
                    foreach ($interview_data as $key => $result) {
                        $rate_int_id = $result->interviewerID;
                        $rating_value    = str_getcsv($result->rating_value);
                        $rating_value    = str_getcsv($result->rating_value);
                        $total_criteria = str_getcsv($result->interview_criteriaID);
                        $rating_value_record[]  = number_format(floatval((array_sum($rating_value)/count($total_criteria))),1);
                        $interviewerName = Helper::getUserDetails($result->interviewerID);
                        $interview_details['evaluated'][]   =  Helper::getAllCondidateDetails(str_getcsv($result->interview_criteriaID),$rating_value,$interviewerName,$result->comment); 
                    }
                    
                 }   
                    
                $pending_int_ids = str_getcsv($condidate->interviewerID);
                foreach ($same_company_id as $key => $arrData) {
                       $arr_usedID[] = $arrData;
                }  
                foreach ($pending_int_ids as $key => $pid) {
                    
                    if($pid!=$rate_int_id && (in_array($pid, $arr_usedID)))
                    {
                        /*if($pid!=null){
                            $interviewerName2 = Helper::getUserDetails($pid);
                            $rating_value    = [];
                            $criteriaIDs = str_getcsv($condidate->criteriaID);
                            $comments = $condidate->comment;
                            $interview_details['pending'][]   =  Helper::getAllCondidateDetails($criteriaIDs,$rating_value,$interviewerName2,$comments); 
                        }*/
                    } 
                 }
                
            $arr_data[] = ['condidateDetail'=>$condidate_detail, 'ratingDetail'=> $interview_details];
            }
            $interview_details=[];  
            $rate_int_id =null;    
        }  
        return $arr_data; 
    }
    /*--------Join Evaluation------*/
    public function joinEvaluation(Request $request, Interview $interview)
    {
        $condidate_id   =   ($request->get('condidateID'))?$request->get('condidateID'):0;
        $user_id        =   ($request->get('userID'))?$request->get('userID'):0;

        $condidate      = Interview::query();
        $condidate      =  $condidate->whereRaw('FIND_IN_SET('.$user_id.',interviewerID)')
                                    ->where('id',$condidate_id); 
        $condidate      =   $condidate->first(); 

        $data = [];
        if($condidate !=null)
        {
           // $cdata['condidateID'] = $condidate->id;
            //$cdata['date'] = \Carbon\Carbon::parse($condidate->created_at)->format('d/m/Y');
          //  $data['interviewee'] = $cdata; 
        }else{
            return  response()->json([ 
                    "status"=>0,
                    "code"=> 404,
                    "message"=>"Record not found ",
                    "data"  =>  $data
                   ]
                );
        }
        $user_result = Helper::getUserDetails($user_id);
        //$data['interviewer'] = ['interviewerID'=>$user_id];
        $data['comment'] = []; 
        $rating_detail = InterviewRating::whereHas('CondidateInterview',function($q) use($condidate_id,$user_id){
            $q->where('condidateID',$condidate_id);
            $q->where('interviewerID',$user_id);
        })->first();

        if($rating_detail!=null){
            $data['comment'] = $rating_detail->comment;
            $data['date'] = \Carbon\Carbon::parse($condidate->created_at)->format('m/d/Y');
            $data['rating'] = "";
            $criteria       = str_getcsv($rating_detail->interview_criteriaID);
            $rating_value   = str_getcsv($rating_detail->rating_value);
            $comment        = $rating_detail->comment;  
            $criteria_list = Criteria::whereIn('id',$criteria)->get();  
            $rating_val = (array_combine($criteria,$rating_value));
            
            foreach ($criteria_list as $key =>$value) { 
                $rated_data['criteriaID'] =  $value->id;
                $rated_data['criteriaName'] =  $value->interview_criteria;
                $rated_data['RatingValue'] =  $rating_val[$value->id];
                $rated_data['feedback'] = Helper::getRatingFeedback($rating_val[$value->id]);
                
                $data['rating'][] =  $rated_data;
            } 
        }else{
            $data['rating'] = "";
            $criteria       = str_getcsv($condidate->criteriaID);
            $rating_value   = [];
            $comment        = $condidate->comment;  
            $criteria_list = Criteria::whereIn('id',$criteria)->get();  
             
            
            foreach ($criteria_list as $key =>$value) { 
                $rated_data['criteriaID'] =  $value->id;
                $rated_data['criteriaName'] =  $value->interview_criteria;
                $rated_data['RatingValue'] =  "";
                $rated_data['feedback'] = Helper::getRatingFeedback("");
                $data['date'] = \Carbon\Carbon::parse($condidate->updated_at)->format('m/d/Y');
                $data['rating'][] =  $rated_data;
            }  
        }  

        $feedback = RatingFeedback::all();
        $feedbackData =[];
        foreach ($feedback  as $key => $value) {
            $data['feedbackRating'][] = ['rating'=> $value->rating_value  ,'value'=> $value->feedback];
        }
        //dd($feedback);
        return  response()->json([ 
                    "status"=>1,
                    "code"=> 200,
                    "message"=>"Record found successfully.",
                    "data"  =>  $data, 
                   ]
                ); 
    }
    /*Save evaulation */
    public function saveEvaluation(Request $request, InterviewRating $evaluate_interview)
    {
        $evaluate_interview = new InterviewRating; 
        $evaluate_interview->condidateID             =   $request->get('condidateID');
        $evaluate_interview->interviewerID           =   $request->get('interviewerID');
       
        $validator = Validator::make($request->all(), [
                'condidateID' => 'required',
                'interviewerID' => 'required',
                'rating' => 'required'  
            ]);  
        
        if ($validator->fails()) {
            $error_msg  =   ['Something went wrong!'];  
            return Response::json(array(
                'status' => 0,
                'message' => $error_msg[0],
                'data'  =>  array()
                )
            );
        }else{
            $query = InterviewRating::query(); 
            $evaluated_record = $query->where('condidateID',$request->get('condidateID'))->where('interviewerID',$request->get('interviewerID'));

            if($evaluated_record->count()>=1){
                $e_r = $evaluated_record->first();
                $evaluate_interview_id = $e_r->id; 
                $evaluate_interview = InterviewRating::find($evaluate_interview_id); 
            }
        } 
        $rating = $request->get('rating'); 
        $criteriaID     =   "";
        $RatingValue    =   "";
        $total_rating   =   0;

        foreach ($rating as $key => $value) {
            $criteriaID     =  $criteriaID.','.$value['criteriaID'];
            $RatingValue    =  $RatingValue.','.$value['RatingValue'];
            $total_rating   =  $total_rating+$value['RatingValue'];
         }
        $total_criteria =   count($rating);
        $evaluate_interview->interview_criteriaID   =   ltrim($criteriaID,',');
        $evaluate_interview->rating_value           =   ltrim($RatingValue,',');
        $evaluate_interview->rating                 =   number_format(floatval(($total_rating/$total_criteria)),1);
        $evaluate_interview->rating_status          =   "Evaluated";
        $evaluate_interview->comment                =   $request->get('comment');
        //dd($evaluate_interview);
        $evaluate_interview->save();
        $request->EvaluationID = $evaluate_interview->id;
       //update status after evaluate
       // $condidateObj = Interview::find($request->get('condidateID'));
       // $condidateObj->rating_status = "Evaluated";
       // $condidateObj->rating = number_format(floatval(($total_rating/5)),1);
       // $condidateObj->save();

        $candidate_name = Interview::find($request->get('condidateID'));
        $cname ='';
        if(count($candidate_name)>0){
            $cname = current(explode(' ',$candidate_name->condidate_name));
        }
        $cname = !empty($cname)?$cname:'';
        return response()->json([ 
                    "status"=>1,
                    "code"=> 200,
                    "message"=>"You've successfully evaluated ".$cname."!",
                    "data"  =>  $request->all(), 
                   ]
                ); 
         
    }
   public function getRecentRecord(Request $request,Interview $interview)
   {
       // $user_auth = JWTAuth::toUser($request->get('deviceToken'));

        $uid = isset($user_auth->userID)?$user_auth->userID:'';
        $user_id = ($request->get('userID'))?$request->get('userID'):$uid;
        $is_user_exist = Helper::isUserExist($user_id);
        $search = $request->get('search');
        if(!$is_user_exist){
            return  response()->json([ 
                    "status"    =>  0,
                    "message"   =>  "Record not found",
                    'data'      =>  []
                   ]
                );
        }
        $corp_profile       = CorporateProfile::where('userID',$user_id)->get();
        $corp_profile_name  = $corp_profile->lists('company_url','userID');
        $user_from_same_company = CorporateProfile::where('company_url',$corp_profile_name[$user_id])->get();
        
        $get_interviewer_list = User::whereIn('userID', $user_from_same_company->lists('userID'))
                                     ->where('status',1)->get();

       // $condidate =  Interview::whereRaw('FIND_IN_SET('.$user_id.',interviewerID)')->get();
        $query      =  Interview::query();
        $query->whereRaw('FIND_IN_SET('.$user_id.',interviewerID)');
        $condidate  =  $query->get();
        
        if($condidate->count()==0)
        {
           $r=  response()->json([ 
                    "status"    =>  0,
                    "message"   =>  "Recent interview data not found",
                    'data'      =>  []
                   ]
                ); 
        } 
        $my_data = [];
        foreach ($condidate as $key => $condidate_record) {
          
            $login_user_id = str_getcsv($condidate_record->interviewerID);
            $int_list = $get_interviewer_list->lists('userID')->toArray();

            if(count(array_intersect($login_user_id,$int_list))>0) {
 
                $interviewer_data = Helper::getInterviewerFromInterview($condidate_record->interviewerID,$condidate_record->id);
                 
                $rating = Helper::getRatingByCondidateID($condidate_record->id);
                // Rating pending if not rated by all user
                $is_evaluate = InterviewRating::where('condidateID',$condidate_record->id)->count();   
                $interviewer_count =  count($interviewer_data);
                $condidate_count_evaluation = $is_evaluate;

                $is_future     = \Carbon\Carbon::parse($condidate_record->interview_date)->isFuture();
                $is_past       = \Carbon\Carbon::parse($condidate_record->interview_date)->isPast();
                
                if($is_future===true){
                    $rs = "Upcoming";
                }elseif ($is_past===true) {
                     $rs = "pending";
                }else{
                    $rs = "pending"; 
                }
                $myEvaluation = InterviewRating::where('condidateID',$condidate_record->id)
                                            ->where('interviewerID',$user_id)->count(); 

                $myStatus =  Interview::whereRaw('FIND_IN_SET('.$user_id.',interviewerID)')
                                        ->where('id',$condidate_record->id)->count();
                
                $avg_ppl = count($login_user_id);   
                $final_rating = Helper::getRatingByCondidateID($condidate_record->id);            
                $final_rating = number_format(floatval($final_rating),1);            
               // if($rs!="Upcoming"){
                $my_data[] =   [
                                'condidateName' =>  $condidate_record->condidate_name,
                                'condidateID' =>  $condidate_record->id,
                                'shortDescription' => $condidate_record->short_description,
                               // 'comment'   =>    $condidate_record->comment,
                                'rating'    => $final_rating,// Helper::getRatingByCondidateID($condidate_record->id),
                                'ratingStatus' => ($interviewer_count==$condidate_count_evaluation)?'Evaluated':"Pending",
                                'myStatus' => ($myStatus)?1:0,
                                'myEvaluation' =>  ($myEvaluation)?1:0,
                                'interviewDetail' => $interviewer_data
                            ];
              //  }
            }    
        }
        $temp_data = [];
        $condidate =''; 
        $data= [];   
        foreach ($user_from_same_company as $key => $user_record) {
            //$condidate =  Interview::whereRaw('FIND_IN_SET('.$user_record->userID.',interviewerID)')->get();
            $query =  Interview::query();
            //$query->whereRaw('FIND_IN_SET('.$user_record->userID.',interviewerID)');
            $condidate =  $query->get();
       
        foreach ($condidate as $key => $condidate_record) { 

            $login_user_id = str_getcsv($condidate_record->interviewerID);
            $int_list = $get_interviewer_list->lists('userID')->toArray();
            if(count(array_intersect($login_user_id,$int_list))>0) {
            
                $interviewer_all_data = Helper::getInterviewerFromInterview($condidate_record->interviewerID,$condidate_record->id);
                $rating   = Helper::getRatingByCondidateID($condidate_record->id);
                // Rating pending if not rated by all user
                $is_evaluate = InterviewRating::where('condidateID',$condidate_record->id)->count();   
                $interviewer_count =  count($interviewer_all_data);
                $condidate_count_evaluation = $is_evaluate;
                $is_future     = \Carbon\Carbon::parse($condidate_record->interview_date)->isFuture();
                $is_past       = \Carbon\Carbon::parse($condidate_record->interview_date)->isPast();
                
                $myEvaluation = InterviewRating::where('condidateID',$condidate_record->id)
                                            ->where('interviewerID',$user_id)->count(); 
                
                $myStatus =  Interview::whereRaw('FIND_IN_SET('.$user_id.',interviewerID)')
                                        ->where('id',$condidate_record->id)->count();
            
                if($is_future===true){
                    $rs = "Upcoming";
                }elseif ($is_past===true) {
                     $rs = "pending";
                }else{
                    $rs = "pending"; 
                }
                 $avg_ppl = count($login_user_id);   
                 $final_rating = Helper::getRatingByCondidateID($condidate_record->id);            
                 $final_rating = number_format(floatval($final_rating),1);
                  // if($rs!="Upcoming"){
                        $temp_data[] =   [
                                    'condidateName' =>  $condidate_record->condidate_name,
                                    'condidateID' =>  $condidate_record->id,
                                    'shortDescription' => $condidate_record->short_description,
                                   // 'comment'   =>    $condidate_record->comment,
                                    'rating'    => $final_rating, //Helper::getRatingByCondidateID($condidate_record->id),
                                    //'ratingStatus' => ($rating>0)?'Evaluated':"Pending",
                                    'ratingStatus' => ($interviewer_count==$condidate_count_evaluation)?'Evaluated':"Pending",
                                    'myStatus' => ($myStatus)?1:0, 
                                    'myEvaluation' =>  ($myEvaluation)?1:0,
                                    'interviewDetail' => $interviewer_all_data
                                ]; 
                  //  }
                }                
            }
        }

        $all_data = array();
        foreach ($temp_data as $key => $value) {
                 $all_data[$value['condidateID']] = $value;

            }
        foreach ($all_data as $key => $value) {
               
               $data[] =   $value;  

        }        
        $search = 'date';
        switch ($search) {
         case 'name':
             $data = $this->array_msort($data, array('condidateName'=>SORT_ASC));
             break;
         case 'date':
              $data = $this->array_msort($data, array('condidateID'=>SORT_DESC));
             break;
         case 'rate':
              $data = $this->array_msort($data, array('rating'=>SORT_DESC));
             break;
        case 'status':
              $data = $this->array_msort($data, array('rating'=>SORT_ASC));
             break;     
         default:
            //$query->orderBy('id','DESC'); 
             break;
        }

        switch ($search) {
         case 'name':
             $my_data = $this->array_msort($my_data, array('condidateName'=>SORT_ASC));
             break;
         case 'date':
              $my_data = $this->array_msort($my_data, array('condidateID'=>SORT_DESC));
             break;
         case 'rate':
              $my_data = $this->array_msort($my_data, array('rating'=>SORT_DESC));
             break;
        case 'status':
              $my_data = $this->array_msort($my_data, array('rating'=>SORT_ASC));
             break;     
         default:
             break;
        }

        if($search)
        {
           $data = array_values($data); 
           $my_data = array_values($my_data);
        } 
        return  response()->json([ 
                    "status"=>1,
                    "code"=> 200,
                    "message"=>"Record found successfully.",
                    'data' => ['all'=>$data,'mine'=>$my_data]
                   ]
                ); 

   }
    public function InviteUser(Request $request,InviteUser $inviteUser)
    {   
        $user =   $inviteUser->fill($request->all()); 
       
        $user_id = $request->get('userID'); 
        $invited_user = User::find($user_id); 
        
        $user_first_name = $invited_user->first_name ;
        $download_link = "http://google.com";
        $user_email = $request->get('email');

        $helper = new Helper;
        $cUrl =$helper->getCompanyUrl($user_email);
        $user->company_url = $cUrl; 
        /** --Send Mail after Sign Up-- **/
        
        $user_data     = User::find($user_id); 
        $sender_name     = $user_data->first_name;
        $invited_by    = $invited_user->first_name.' '.$invited_user->last_name;
        $receipent_name = "User";
        $subject       = ucfirst($sender_name)." has invited you to join a Udex";   
        $email_content = array('receipent_email'=> $user_email,'subject'=>$subject,'name'=>'User','invite_by'=>$invited_by,'receipent_name'=>ucwords($receipent_name));
        $helper = new Helper;
        $invite_notification_mail = $helper->sendNotificationMail($email_content,'invite_notification_mail',['name'=> 'User']);
        $user->save();

        return  response()->json([ 
                    "status"=>1,
                    "code"=> 200,
                    "message"=>"You've invited your colleague, nice work!",
                    'data' => ['receipentEmail'=>$user_email]
                   ]
                );

    }
    
    /*
    *  Method : Get condidate Record
    *
    */

    public function getCondidateRecord(Request $request,Interview $interview)
    {
        
        $user_auth = JWTAuth::toUser($request->get('deviceToken'));
        $uid = isset($user_auth->userID)?$user_auth->userID:'';
        $user_id = ($request->get('userID'))?$request->get('userID'):$uid;
        
        $is_user_exist = Helper::isUserExist($user_id);
        $search = $request->get('search');
        $condidate_id =  $request->get('directoryID');

        $c = InterviewRating::where('condidateID',$condidate_id)->get();  

        $comments = [];
         
        if($c->count()>0){
            $interview_criteriaID =[];
            foreach ($c as $key => $result) {

                $rating_value    = str_getcsv($result->rating_value);
                $interviewerName = Helper::getUserDetails($result->interviewerID);
                
                if( !empty($result->comment))
                {
                  $comments[]  =[
                            'firstName' => $interviewerName['firstName'],
                            'lastName'  => $interviewerName['lastName'],
                            'comment'   => $result->comment];
                }    
                
            }
        }
 

        $c_details = Interview::find($condidate_id);
        $date    = \Carbon\Carbon::parse($c_details->created_at)->format('m/d/Y');
        $data['date'] = $date ;
        if(!$is_user_exist){
            return  response()->json([ 
                    "status"    =>  0,
                    "message"   =>  "Record not found",
                    'data'      =>  []
                   ]
                );
        }
        $corp_profile       = CorporateProfile::where('userID',$user_id)->get();
        $corp_profile_name  = $corp_profile->lists('company_url','userID');
        $user_from_same_company = CorporateProfile::where('company_url',$corp_profile_name[$user_id])->get();
        
        $get_interviewer_list = User::whereIn('userID', $user_from_same_company->lists('userID'))
                                     ->where('status',1)->get();
                             
       // $condidate =  Interview::whereRaw('FIND_IN_SET('.$user_id.',interviewerID)')->get();
        $query =  Interview::query();
        $query->where('id',$condidate_id);
        $condidate =  $query->get();
        
        if($condidate->count()==0)
        {
           return  response()->json([ 
                    "status"    =>  0,
                    "message"   =>  "Condidate record not found",
                    'data'      =>  []
                   ]
                ); 
        } 
        $my_data = [];
        foreach ($condidate as $key => $condidate_record) {
             
            $interviewer_data = Helper::getInterviewerFromInterview($condidate_record->interviewerID,$condidate_record->id);
             
            $rating = Helper::getRatingByCondidateID($condidate_record->id);
            // Rating pending if not rated by all user
            $is_evaluate = InterviewRating::where('condidateID',$condidate_record->id)->count();   
            $interviewer_count =  count($interviewer_data);
            $condidate_count_evaluation = $is_evaluate;

            $is_future     = \Carbon\Carbon::parse($condidate_record->interview_date)->isFuture();
            $is_past       = \Carbon\Carbon::parse($condidate_record->interview_date)->isPast();
            

            if($is_future===true){
                $rs = "Upcoming";
            }elseif ($is_past===true) {
                 $rs = "pending";
            }else{
                $rs = "pending"; 
            }
                                   
            if($rs!="Upcoming"){
            $my_data[] =   [
                            
                            'condidateID' =>  $condidate_record->id,
                            'rating'    => Helper::getRatingByCondidateID($condidate_record->id),
                            'ratingStatus' => ($interviewer_count==$condidate_count_evaluation)?'Evaluated':"Pending",
                            'details' => $interviewer_data,
                            'comment'   =>   $comments
                        ];
            }
        }
        $temp_data = [];

        $condidate =''; 
        $data= [];

        foreach ($user_from_same_company as $key => $user_record) {
            //$condidate =  Interview::whereRaw('FIND_IN_SET('.$user_record->userID.',interviewerID)')->get();
            $query =  Interview::query();
            $query->where('id',$condidate_id);
            $condidate =  $query->get();

            foreach ($condidate as $key => $condidate_record) {
            
            $interviewer_all_data = Helper::getInterviewerFromInterview($condidate_record->interviewerID,$condidate_record->id);
            $rating   = Helper::getRatingByCondidateID($condidate_record->id);
            // Rating pending if not rated by all user
            $is_evaluate = InterviewRating::where('condidateID',$condidate_record->id)->count();   
            $interviewer_count =  count($interviewer_all_data);
            $condidate_count_evaluation = $is_evaluate;
            $is_future     = \Carbon\Carbon::parse($condidate_record->interview_date)->isFuture();
            $is_past       = \Carbon\Carbon::parse($condidate_record->interview_date)->isPast();
            
           // $rated_data = InterviewRating::where('condidateID',$condidate_record->id)->first();   
            if($is_future===true){
                $rs = "Upcoming";
            }elseif ($is_past===true) {
                 $rs = "pending";
            }else{
                $rs = "pending"; 
            } 
            $temp_data[] =   [
                            'condidateID' =>  $condidate_record->id, 
                            'rating'    => Helper::getRatingByCondidateID($condidate_record->id),
                            'ratingStatus' => ($interviewer_count==$condidate_count_evaluation)?'Evaluated':"Pending",
                            'details' => $interviewer_all_data,
                            'comment'   =>   $comments
                        ]; 
                       
            }
        }

        $all_data = array();
        foreach ($temp_data as $key => $value) {
                 $all_data[$value['condidateID']] = $value;

            }

        foreach ($all_data as $key => $value) {

               $data =   $value;  
        } 
        $data['date'] = $date ;       
        $collection = collect($data);

        $filtered = $collection->except(['condidateID']);

        $data = $filtered->all();
         
        return  response()->json([ 
                    "status"=>1,
                    "code"=> 200,
                    "message"=>"Record found successfully.",
                    'data' => $data
                   ]
                ); 
    }

} 