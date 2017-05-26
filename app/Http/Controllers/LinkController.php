<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Jenssegers\Agent\Agent;
use DB;
use Log;
use App\Link;
use App\Click;
use App\User_id;

class LinkController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $links = Link::all();
        return view('links.listing', [ 'links' => $links ]);

    }
    public function create(Request $request)
    {
        $this->validate($request, [
            'target_url' => 'active_url'
            ]);

        $i=0;
        do{
            $i++;
            $link_token =  str_random(20); 
            $num_rec = Link::where('token', $link_token)->count();

        } while($num_rec >0 && $i <4);

        if ($num_rec >0){
            Log::error("Something gone wrong. Link token collision.");
            return redirect()->withErrors("Не удалось создать уникальный token. Свяжитесь с администратором системы.")->route('linkLinks');
        }

        $links = new Link;
        $links->token = $link_token;
        $links->target_url = $request->target_url;  
        $links->save();
        return redirect()->route('linkLinks');
    }

/**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function viewStat($id = '123')
    {
        //
        $curent_link = Link::where('token', $id)->first();
        $links = Link::all();
        if ($curent_link == null) {
            $id = '';
            return view('links.statistics',[ 'links' => $links, 'curent_link' => $curent_link]);
        }

        $clicks = Click::where('link_id', $curent_link->id)->get();
        $num_click = $clicks->count();
        return view('links.statistics',[ 'links' => $links, 'clicks' => $clicks, 'curent_link' => $curent_link, 'num_click' => $num_click ]);   
    }

    public function viewUsers($id = '0')
    {
        //
        $users = User_id::all();
    
        return view('links.users',[ 'users' => $users, 'user_id' => $id ] );
    }

    public function viewUserStat($id = '0')
    {
        //
        if ($id != '0'){
            $curent_user = User_id::where('token', $id)->first();
            $clicks = Click::where('user_id', $curent_user->id)->get();
            $num_link = $clicks->count();
        }else{
            $curent_user = null;
            $clicks = null;
            $num_link = 0;
        }
        $users = User_id::all(['id', 'token']);
        return view('links.userstat', [ 'users' => $users, 'curent_user' => $curent_user, 'clicks' => $clicks, 'num_link' => $num_link ] );
    }

    public function redirect(Request $request, $link_token)
    {
        $curent_ip = $request ->ip();
        $agent = new Agent();
        $agent->setUserAgent($request->header('User-Agent')); 
        $curent_link = Link::where('token', $link_token)->first();
        if($agent->isRobot()){
            return redirect($curent_link->target_url);
        }

        $user_token = Cookie::get('uid');

        if($user_token != null){
            $user_id = User_id::where('token', $user_token)->first();
        }else{
            $user_id = null;
        }
        if($user_id == null){

            $user_token = str_random(20);
            $user_id = new user_id;
            $user_id ->token = $user_token;
            $user_id ->browser = $agent->browser();
            $user_id ->os = $agent->platform();;
            $user_id ->link_id = $curent_link ->id;
            $user_id ->save();
        }
        $curent_click = new Click;
        $curent_click ->link_id = $curent_link ->id;
        $curent_click ->link_url = $curent_link ->target_url;
        $curent_click ->user_id = $user_id ->id;
        if ($curent_ip == '::1'){
            $curent_click ->ip = '127.0.0.1';
        }else{
            $curent_click ->ip = $curent_ip;
        }
        $curent_click ->user_token = $user_id ->token;
        $curent_click ->user_ua = $request->header('User-Agent');
        $curent_click ->save();
        return redirect($curent_link->target_url)->withCookie(cookie('uid', $user_token));
    }
}
