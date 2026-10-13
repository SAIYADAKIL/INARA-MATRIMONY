<?php
define('BASEPATH', TRUE);

class FakeInteractionResult
{
    private $rows;

    public function __construct($rows)
    {
        $this->rows = $rows;
    }

    public function result()
    {
        return $this->rows;
    }
}

class FakeInteractionDatabase
{
    public $events = array();

    public function query($sql, $bindings)
    {
        return new FakeInteractionResult($this->events);
    }
}

class FakeInteractionLoader
{
    public function database()
    {
    }
}

class CI_Model
{
    public $db;
    public $load;

    public function __construct()
    {
        $this->db = $GLOBALS['fake_interaction_db'];
        $this->load = new FakeInteractionLoader();
    }
}

function log_message($level, $message)
{
}

require __DIR__ . '/../application/models/Profile_interaction_model.php';

$GLOBALS['fake_interaction_db'] = new FakeInteractionDatabase();
$model = new Profile_interaction_model();
$state_method = new ReflectionMethod('Profile_interaction_model', 'state_from_metadata');
$state_method->setAccessible(TRUE);
$assertions = 0;
$assert = function ($condition, $message) use (&$assertions) {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
};

$assert(
    $state_method->invoke($model, 'save', '{"state":"saved"}') === 'saved',
    'Saved event metadata is recognized.'
);
$assert(
    $state_method->invoke($model, 'save', '{"state":"removed"}') === 'removed',
    'Removed event metadata is recognized.'
);
$assert(
    $state_method->invoke($model, 'block', '{"state":"unblocked"}') === 'unblocked',
    'Unblocked event metadata is recognized.'
);
$assert(
    $state_method->invoke($model, 'block', NULL) === 'blocked',
    'Legacy block events without metadata remain blocked.'
);
$assert(
    $state_method->invoke($model, 'block', '{"invalid":true}') === 'blocked',
    'Malformed block metadata defaults to the privacy-preserving blocked state.'
);

$GLOBALS['fake_interaction_db']->events = array(
    (object) array('user_id' => 11, 'target_user_id' => 5, 'metadata' => '{"state":"blocked"}'),
    (object) array('user_id' => 5, 'target_user_id' => 12, 'metadata' => '{"state":"unblocked"}'),
    (object) array('user_id' => 13, 'target_user_id' => 5, 'metadata' => NULL),
    (object) array('user_id' => 5, 'target_user_id' => 14, 'metadata' => '{"invalid":true}'),
    (object) array('user_id' => 5, 'target_user_id' => 15, 'metadata' => '{"state":"unblocked"}')
);
$blocked_ids = $model->blocked_user_ids(5);
sort($blocked_ids);
$assert($blocked_ids === array(11, 13, 14), 'Both block directions are honored and unblocked pairs are omitted.');

echo 'PASS: ' . $assertions . ' profile interaction assertions.' . PHP_EOL;
