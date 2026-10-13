<?php
define('BASEPATH', __DIR__ . DIRECTORY_SEPARATOR);

class CI_Controller
{
}

class CI_Model
{
}

class FakeAuthSession
{
    public $values = array('logged_in' => TRUE, 'user_id' => 15, 'user_name' => 'Test Member');
    public $regenerations = 0;
    public $destroyed = FALSE;
    public $flash = array();

    public function userdata($key)
    {
        return isset($this->values[$key]) ? $this->values[$key] : NULL;
    }

    public function sess_regenerate($destroy = FALSE)
    {
        $this->regenerations++;
    }

    public function sess_destroy()
    {
        $this->destroyed = TRUE;
    }

    public function set_flashdata($key, $value)
    {
        $this->flash[$key] = $value;
    }
}

class FakeAuthInput
{
    public $values = array();

    public function method($upper = FALSE)
    {
        return 'POST';
    }

    public function post($key)
    {
        return isset($this->values[$key]) ? $this->values[$key] : NULL;
    }
}

class FakeAuthUserModel
{
    public $user;
    public $updated_hash;
    public $update_succeeds = TRUE;

    public function get_by_id($user_id)
    {
        return $this->user;
    }

    public function update_password($user_id, $password_hash)
    {
        $this->updated_hash = $password_hash;
        return $this->update_succeeds;
    }
}

class FakeAuthInterestModel
{
    public function navigation_counts($user_id)
    {
        return array();
    }
}

class FakeAuthLoader
{
    public $view_data;

    public function model($name)
    {
    }

    public function view($name, $data)
    {
        $this->view_data = $data;
    }
}

function redirect($uri)
{
    $GLOBALS['auth_test_redirect'] = $uri;
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR .
    'application' . DIRECTORY_SEPARATOR .
    'controllers' . DIRECTORY_SEPARATOR . 'Auth.php';

function create_auth_test_controller($input_values)
{
    $reflection = new ReflectionClass('Auth');
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->session = new FakeAuthSession();
    $controller->input = new FakeAuthInput();
    $controller->input->values = $input_values;
    $controller->User_model = new FakeAuthUserModel();
    $controller->User_model->user = (object) array(
        'id' => 15,
        'status' => 'active',
        'password' => password_hash('CurrentPass1', PASSWORD_DEFAULT)
    );
    $controller->Interest_model = new FakeAuthInterestModel();
    $controller->load = new FakeAuthLoader();
    return $controller;
}

function auth_assert($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

$assertions = 0;

$controller = create_auth_test_controller(array(
    'current_password' => 'CurrentPass1',
    'new_password' => 'NewStrong9',
    'confirm_password' => 'NewStrong9'
));
$controller->change_password();
$assertions++;
auth_assert(
    TRUE,
    password_verify('NewStrong9', $controller->User_model->updated_hash),
    'a valid new password is hashed and saved'
);
$assertions++;
auth_assert(1, $controller->session->regenerations, 'successful password change regenerates the session');
$assertions++;
auth_assert('change-password', $GLOBALS['auth_test_redirect'], 'successful change redirects to the password page');

$controller = create_auth_test_controller(array(
    'current_password' => 'wrong',
    'new_password' => 'NewStrong9',
    'confirm_password' => 'NewStrong9'
));
$controller->change_password();
$assertions++;
auth_assert('Your current password is incorrect.', $controller->load->view_data['error'], 'wrong current password is rejected');
$assertions++;
auth_assert(NULL, $controller->User_model->updated_hash, 'wrong current password does not update the password');

$controller = create_auth_test_controller(array(
    'current_password' => 'CurrentPass1',
    'new_password' => 'weak',
    'confirm_password' => 'weak'
));
$controller->change_password();
$assertions++;
auth_assert(
    'Your new password must be at least 8 characters.',
    $controller->load->view_data['error'],
    'short new password is rejected'
);

$controller = create_auth_test_controller(array(
    'current_password' => 'CurrentPass1',
    'new_password' => 'NewStrong9',
    'confirm_password' => 'Different9'
));
$controller->change_password();
$assertions++;
auth_assert(
    'The new password and confirmation do not match.',
    $controller->load->view_data['error'],
    'password confirmation mismatch is rejected'
);

$controller = create_auth_test_controller(array(
    'current_password' => 'CurrentPass1',
    'new_password' => 'NewStrong9',
    'confirm_password' => 'NewStrong9'
));
$controller->User_model->update_succeeds = FALSE;
$controller->change_password();
$assertions++;
auth_assert(
    'Unable to update your password. Please try again.',
    $controller->load->view_data['error'],
    'persistence failure is reported without a success response'
);
$assertions++;
auth_assert(0, $controller->session->regenerations, 'failed persistence does not regenerate the session as a success');

echo 'PASS: ' . $assertions . ' password-change assertions' . PHP_EOL;
