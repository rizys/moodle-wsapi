<?php

define('CLI_SCRIPT', true);
require_once('config.php');
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/clilib.php');

function setCoreValue($name, $new_value)
{
    $data = get_config('moodle', $name);
    if (!is_null($data)) {
        if ($data != $new_value) {
            $old_value = $data;
            set_config($name, $new_value);
            echo "update core param $name (changed $old_value to $new_value)\n";
        }
    } else {
        set_config($name, $new_value);
        echo "insert core param $name with value $new_value\n";
    }
}

function implementRole($short, $name, $desc, $caps = [])
{
    global $DB;
    $role = $DB->get_record('role', ['shortname' => $short], 'id');
    if (is_object($role)) {
        $roleid = $role->id;
        echo "$short role already exists\n";
    } else {
        $roleid = create_role($name, $short, $desc);
        echo "$short role created\n";
    }
    $context = context_system::instance();
    // Permet à l'administrateur d'attribuer ce rôle via l'interface
    set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
    $oldcaps = array_keys(role_context_capabilities($roleid, $context));
    $capstodisable = array_diff($oldcaps, $caps);
    $capstoenable = array_diff($caps, $oldcaps);
    foreach ($capstodisable as $cap) {
        unassign_capability($cap, $roleid, $context);
        echo "$short role $cap capability removed\n";
    }
    foreach ($capstoenable as $cap) {
        assign_capability($cap, CAP_ALLOW, $roleid, $context);
        echo "$short role $cap capability added\n";
    }
}


function specialUser($user, $roles = [])
{
    global $DB, $CFG;
    if (!is_object($user)) {
        $user = (object)$user;
    }
    require_once $CFG->dirroot . '/user/lib.php';
    $record = $DB->get_record('user', ['username' => $user->username], 'id');
    if (is_object($record)) {
        $user->id = $record->id;
        user_update_user($user, false);
        echo "$user->username special user updated if required\n";
    } else {
        $user->password = 'token';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->id = user_create_user($user, false);
        echo "$user->username special user created\n";
    }
    $context = context_system::instance();
    $oldrolesobj = get_user_roles($context, $user->id);
    $oldroles = [];
    foreach ($oldrolesobj as $roleobj) {
        $oldroles[] = $roleobj->shortname;
    }
    $rolestodisable = array_diff($oldroles, $roles);
    $rolestoenable = array_diff($roles, $oldroles);
    foreach ($rolestodisable as $role) {
        $roleid = $DB->get_record('role', ['shortname' => $role], 'id', MUST_EXIST)->id;
        role_unassign($roleid, $user->id, $context->id);
        echo "$user->username special user $role role removed\n";
    }
    foreach ($rolestoenable as $role) {
        $roleid = $DB->get_record('role', ['shortname' => $role], 'id', MUST_EXIST)->id;
        role_assign($roleid, $user->id, $context->id);
        echo "$user->username special user $role role added\n";
    }
}


/**
 * Activation de l'API WS et du protocole REST
 */
setCoreValue('enablewebservices', '1');
setCoreValue('webserviceprotocols', 'rest');

/**
 * Création d'un rôle système
 */
implementRole('wsapirights', "Droits pour l'API Web service", "capacités nécessaires", [
    'moodle/webservice:createtoken',
    'webservice/rest:use',
    // il faut ajouter ici toute capacité supplémentaire nécessaire pour joindre le Web service utile,
    // qu'il s'agisse d'un Web service existant dans un plugin ou d'un Web service créé directement depuis
    // l'interface d'administration
]);

/**
 * Création d'un utilisateur spécifique pour contacter les Web services
 * Vous devrez modifier le compte de cet utilisateur pour définir un mot de passe
 */
specialUser([
    'username'  => 'wsapi',
    'firstname' => 'Moodle Web Service',
    'lastname'  => 'Compte machine',
    'email'     => 'noreply@moodle.org.invalid' // remplacer cette valeur par votre courriel
], [
    'wsapirights'
]);

