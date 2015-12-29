<?php
/**
 * The Users Controller
 *
 * @author Hemant Mann
 */
use Shared\Controller as Controller;
use Framework\RequestMethods as RequestMethods;
use Shared\Markup as Markup;
use Framework\Registry as Registry;

class Auth extends Controller {
    
    public function login() {
        $this->willRenderLayoutView = false;
        $view = $this->getActionView();
        $return = $this->_checkLogin();

        if ($return && isset($return["error"])) {
            $view->set("error", $return["error"]);
        }
    }

    protected function _checkLogin() {
        if (RequestMethods::post("action") == "logmein") {
            $username = RequestMethods::post("username");
            $password = RequestMethods::post("password");
            $user = User::first(array("username = ?" => $username, "live = ?" => true));

            if (!$user) {
                return array("error" => "Invalid username/password");
            }

            if (!Markup::checkHash($password, $user->password)) {
                return array("error" => "Invalid username/password");
            }
            $session = Registry::get("session");
            $this->setUser($user);

            if ($user->admin) {
                self::redirect("/admin");
            }

            $scholar = Scholar::first(array("user_id = ?" => $user->id));
            if ($scholar) {
                $session->set('scholar', $scholar);
                
                $organization = Organization::first(array("id = ?" => $scholar->organization_id));
                $session->set('organization', $organization);
                self::redirect("/student");
            }

            $organization = Organization::first(array("user_id = ?" => $user->id));
            if ($organization) {
                $session->set('organization', $organization);
                self::redirect("/school");
            }

            $educator = Educator::first(array("user_id = ?" => $user->id));
            if ($educator) {
                $session->set('educator', $educator);
                
                $organization = Organization::first(array("id = ?" => $educator->organization_id));
                $session->set('organization', $organization);
                self::redirect("/teacher");
            }

            return array("error" => "Something went wrong please try later");

        } else {
            return null;
        }
        
    }

    /**
     * Creates new Users with unique username
     * @return null
     */
    protected function _createUser($opts) {
        $name = $opts["name"];
        $email = $opts["email"];
        $phone = $opts["phone"];

        $last = \User::first(array(), array("id"), "created", "desc");
        $id = (int) $last->id + 1;
        $prefix = strtolower(array_shift(explode(" ", $this->organization->name)));
        
        if (Markup::checkValue($email)) {
            $found = \User::first(array("email = ?" => $email), array("id"));
            if ($found) {
                throw new \Exception("Email already exists");
            }
        }
        if (Markup::checkValue($phone)) {
            $found = \User::first(array("phone = ?" => $phone), array("id"));
            if ($found) {
                throw new \Exception("Phone number already exists");
            }
        }
        if (empty(Markup::checkValue($name))) {
            return NULL;
            // throw new \Exception("Please provide a name");
        }
        $user = new \User(array(
            "name" => $name,
            "email" => $email,
            "phone" => $phone,
            "username" => $prefix. "_" .$id,
            "password" => Markup::encrypt("password")
        ));
        $user->save();

        return $user;
    }

    protected function reArray(&$array) {
        $file_ary = array();
        $file_keys = array_keys($array);
        $file_count = count($array[$file_keys[0]]);
        
        for ($i=0; $i<$file_count; $i++) {
            foreach ($file_keys as $key) {
                $file_ary[$i][$key] = $array[$key][$i];
            }
        }

        return $file_ary;
    }


}
