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
    /**
     * @protected
     */
    public function _admin() {
        if (!$this->user->admin) {
            self::redirect("/404");
        }
    }

    /**
     * @protected
     */
    public function _session() {
        if ($this->user && $this->user->type != 'central') {
            self::redirect("/". $this->user->type ."s/");
        } elseif ($this->user->type == 'central') {
            self::redirect("/central");
        }
    }

    /**
     * @protected
     */
    public function _secure() {
        $user = $this->getUser();
        $which = strtolower(get_class($this));
        if (!$user) {
            header("Location: /$which/login");
            exit();
        }
    }
    
    protected function setUser($user) {
        $session = Registry::get("session");
        if ($user) {
            $session->set("user", $user->id);
        } else {
            $session->erase("user");
        }
        $this->_user = $user;
        return $this;
    }

    /**
     * @protected
     */
    public function changeLayout() {
        $which = strtolower(get_class($this));
        $check = substr($which, -1);
        if ($check == "s") {
            $which = substr($which, 0, -1);
        }
        $this->defaultLayout = "layouts/$which";
        $this->setLayout();
    }

    /**
     * @before _session
     */
    public function login() {
        $this->willRenderLayoutView = false;
        $view = $this->getActionView();
        $return = $this->_checkLogin();

        if ($return && isset($return["error"])) {
            $view->set("error", $return["error"]);
        }
    }

    public function logout() {
        $which = strtolower(get_class($this));

        switch ($which) {
            case 'students':
            case 'teachers':
                $location = "/$which/login";
                $func = "set".ucfirst($this->user->type);   
                $this->$func(false);     
                break;
            
            default:
                $location = '/';
                break;
        }

        $this->setUser(false);
        self::redirect($location);
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

            $model = ucfirst(strtolower($user->type));
            if ($model != 'Central') {
                $person = $model::first(array("user_id = ?" => $user->id));
                if ($person->organization_id) {
                    $organization = Organization::first(array("id = ?" => $person->organization_id));
                    $session->set('school', $organization);
                }
                switch ($model) {
                    case 'Student':
                        $session->set('student', $person);
                        self::redirect("/students/dashboard");
                        break;
                    
                    case 'Educator':
                        $session->set('teacher', $person);
                        self::redirect("/teachers/dashboard");
                        break;
                }
            } else {
                self::redirect("/central");
            }
        } else {
            return null;
        }
        
    }

    protected function _saveUser($opts) {
        $name = RequestMethods::post("name");
        $email = RequestMethods::post("email");
        $phone = RequestMethods::post("phone");

        if ($opts["type"] == "student") {
            $dob = RequestMethods::post("dob");
            $address = RequestMethods::post("address");
            $parentName = RequestMethods::post("parent");
            $relation = RequestMethods::post("relation");
            $parentPhone = RequestMethods::post("parent_phone");
            $classroom = RequestMethods::post("classroom");
        }

        $last = \User::first(array(), array("id", "created"), "created", "desc");
        $id = $last->id;
        $prefix = strtolower(array_shift(explode(" ", $this->school->name)));
        foreach ($name as $key => $value) {
            if (Markup::checkValue($email["key"])) {
                $found = \User::first(array("email = ?" => $email["key"]));
                if ($found) {
                    return array("error" => "Email already exists for ". $name[$key]);
                }
            }
            $user = new \User(array(
                "name" => $value,
                "email" => $email[$key],
                "phone" => $phone[$key],
                "username" => $prefix. "_" .(++$id),
                "password" => Markup::encrypt("password"),
                "type" => $opts["type"]
            ));
            $user->save();

            if ($opts["type"] == "teacher") {
                $teacher = new \Educator(array(
                    "user_id" => $user->id,
                    "organization_id" => $this->school->id
                ));
                $teacher->save();
            } elseif ($opts["type"] == "student") {
                $parent = new \StudentParent(array(
                    "relation" => $relation[$key],
                    "phone" => $parentPhone[$key],
                    "name" => $parentName[$key]
                ));
                $parent->save();

                $student = new \Student(array(
                    "dob" => $dob[$key],
                    "parent_id" => $parent->id,
                    "address" => $address[$key],
                    "organization_id" => $this->school->id,
                    "roll_no" => "",
                    "user_id" => $user->id
                ));
                $student->save();

                $enrollment = new \Enrollment(array(
                    "student_id" => $student->id,
                    "classroom_id" => $classroom[$key]
                ));
                $enrollment->save();
            }
            
        }
    }
}