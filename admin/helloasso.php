<?php
/* Copyright (C)
 * Author: Ton Nom <ton.email@domaine.com>
 * License: GPL v3
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

if (!$user->admin) accessforbidden();

$langs->load("admin");
$langs->load("helloasso@helloasso");

$page_name = "HelloAsso - Configuration";
$action = GETPOST('action', 'alpha');

// Sauvegarde des paramètres
if ($action == 'update') {
    $webhook_secret = GETPOST('HELLOASSO_WEBHOOK_SECRET', 'alpha');
    $api_key = GETPOST('HELLOASSO_API_KEY', 'alpha');
    $default_user_id = GETPOST('HELLOASSO_DEFAULT_USER_ID', 'int');
    $default_donation = GETPOST('HELLOASSO_DEFAULT_DONATION_LABEL', 'alpha');
    $send_all_emails_to = GETPOST('HELLOASSO_SEND_ALL_EMAILS_TO', 'alpha');

    dolibarr_set_const($db, "HELLOASSO_WEBHOOK_SECRET", $webhook_secret, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "HELLOASSO_API_KEY", $api_key, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "HELLOASSO_DEFAULT_USER_ID", $default_user_id, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "HELLOASSO_DEFAULT_DONATION_LABEL", $default_donation, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "HELLOASSO_SEND_ALL_EMAILS_TO", $send_all_emails_to, 'chaine', 0, '', $conf->entity);

    setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

// Récupération des constantes
$webhook_secret = getDolGlobalString('HELLOASSO_WEBHOOK_SECRET');
$api_key = getDolGlobalString('HELLOASSO_API_KEY');
$default_user_id = getDolGlobalInt('HELLOASSO_DEFAULT_USER_ID');
$default_donation = getDolGlobalString('HELLOASSO_DEFAULT_DONATION_LABEL');
$send_all_emails_to = getDolGlobalString('HELLOASSO_SEND_ALL_EMAILS_TO');

$webhook_url = DOL_MAIN_URL_ROOT.'/custom/helloasso/webhook.php';

// Titre et header
llxHeader('', $langs->trans($page_name));
print load_fiche_titre($langs->trans($page_name), '', 'title_setup');

// Formulaire principal
print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

// URL Webhook (readonly)
print '<tr class="oddeven">';
print '<td>'.$langs->trans("Webhook URL (information)").'</td>';
print '<td><input type="text" readonly value="'.$webhook_url.'" class="quatrevingtpercent"></td>';
print '</tr>';

// Secret HelloAsso
print '<tr class="oddeven">';
print '<td><label for="HELLOASSO_WEBHOOK_SECRET">'.$langs->trans("Webhook Secret").'</label></td>';
print '<td><input type="text" name="HELLOASSO_WEBHOOK_SECRET" value="'.$webhook_secret.'" class="quatrevingtpercent"></td>';
print '</tr>';

// API Key
print '<tr class="oddeven">';
print '<td><label for="HELLOASSO_API_KEY">'.$langs->trans("HelloAsso API Key").'</label></td>';
print '<td><input type="text" name="HELLOASSO_API_KEY" value="'.$api_key.'" class="quatrevingtpercent"></td>';
print '</tr>';

// Identifiant user Dolibarr
print '<tr class="oddeven">';
print '<td><label for="HELLOASSO_DEFAULT_USER_ID">'.$langs->trans("Default Dolibarr User ID").'</label></td>';
print '<td><input type="number" name="HELLOASSO_DEFAULT_USER_ID" value="'.$default_user_id.'" min="1" class="maxwidth100"></td>';
print '</tr>';

// Identifiant donation product
print '<tr class="oddeven">';
print '<td><label for="HELLOASSO_DEFAULT_DONATION_LABEL">'.$langs->trans("Default Dolibarr Donation Label").'</label></td>';
print '<td><input type="text" name="HELLOASSO_DEFAULT_DONATION_LABEL" value="'.$default_donation.'" class="quatrevingtpercent"></td>';
print '</tr>';

// Email send to all
print '<tr class="oddeven">';
print '<td><label for="HELLOASSO_SEND_ALL_EMAILS_TO">'.$langs->trans("Envoyer tous les emails vers (tests notamment)").'</label></td>';
print '<td><input type="text" name="HELLOASSO_SEND_ALL_EMAILS_TO" value="'.$send_all_emails_to.'" class="quatrevingtpercent"></td>';
print '</tr>';

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

// Ligne de séparation
print '<br><hr><br>';

// Section test du webhook
print '<h3>'.$langs->trans("Webhook Test").'</h3>';
print '<p>'.$langs->trans("You can simulate a HelloAsso webhook call to test your integration.").'</p>';
print '<div class="center">';
print '<form method="post" action="'.DOL_URL_ROOT.'/custom/helloasso/webhook.php" target="_blank">';
print '<select name="test">';
require_once __DIR__.'/../tests/TestParser.php';
foreach (TestParser::getScenarioNames() as $scenario) print '<option value="'.$scenario.'">'.$scenario.'</option>';
print '</select>';
print '<input type="submit" class="button" value="'.$langs->trans("Simulate Webhook Call").'">';
print '</form>';
print '</div>';

llxFooter();
$db->close();
