<?php
// This plugin's own login page: a self-contained Firebase email/password
// form. Offered as a "Log in with Firebase" option on Moodle's native login
// page via auth_plugin_firebase::loginpage_idp_list(). On success it redirects
// back to $wantsurl with a fresh Firebase ID token attached, which
// pre_loginpage_hook() then verifies as usual.
require_once(__DIR__ . '/../../config.php');

$wantsurl = optional_param('wantsurl', $CFG->wwwroot, PARAM_LOCALURL);

$projectid = get_config('auth_firebase', 'firebase_project_id');
$apikey = get_config('auth_firebase', 'firebase_api_key');
$authdomain = get_config('auth_firebase', 'firebase_auth_domain');
$appid = get_config('auth_firebase', 'firebase_app_id');
$recaptchakey = get_config('auth_firebase', 'recaptcha_enterprise_key');
$appcheckdebugtoken = get_config('auth_firebase', 'appcheck_debug_token');
$tokenparam = get_config('auth_firebase', 'token_param') ?: 'token';

// The App Check debug token disables attestation, so only ever honour it on a
// site running in developer-debug mode - never on a production instance, even
// if the setting was left populated by mistake.
if (empty($CFG->debugdeveloper)) {
    $appcheckdebugtoken = '';
}

// Values interpolated into the inline <script> below. JSON_HEX_TAG/AMP make a
// </script> (or other markup) breakout impossible regardless of their content.
$jsflags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$PAGE->set_url('/auth/firebase/login.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('loginwithfirebase', 'auth_firebase'));

echo $OUTPUT->header();

if (empty($projectid) || empty($apikey)) {
    echo $OUTPUT->notification(get_string('loginpage_missingconfig', 'auth_firebase'), 'error');
    echo $OUTPUT->footer();
    die;
}
?>
<div id="firebase-login-app" style="max-width: 360px; margin: 2rem auto;">
  <form id="firebase-login-form">
    <div class="mb-3">
      <label for="firebase-email"><?php echo get_string('email'); ?></label>
      <input type="email" id="firebase-email" class="form-control" required autocomplete="username">
    </div>
    <div class="mb-3">
      <label for="firebase-password"><?php echo get_string('password'); ?></label>
      <input type="password" id="firebase-password" class="form-control" required autocomplete="current-password">
    </div>
    <div id="firebase-login-error" class="alert alert-danger" style="display:none"></div>
    <button type="submit" class="btn btn-primary"><?php echo get_string('login'); ?></button>
  </form>
</div>

<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-auth-compat.js"></script>
<?php if (!empty($recaptchakey)): ?>
<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-app-check-compat.js"></script>
<?php endif; ?>
<script>
(function() {
  firebase.initializeApp({
    apiKey: <?php echo json_encode($apikey, $jsflags); ?>,
    authDomain: <?php echo json_encode($authdomain, $jsflags); ?>,
    projectId: <?php echo json_encode($projectid, $jsflags); ?>,
    appId: <?php echo json_encode($appid, $jsflags); ?>
  });

  <?php if (!empty($recaptchakey)): ?>
  <?php if (!empty($appcheckdebugtoken)): ?>
  // Bypasses reCAPTCHA App Check attestation (e.g. for a test/local domain
  // the reCAPTCHA key isn't registered for). Only takes effect when this
  // setting is explicitly filled in - leave blank in production.
  self.FIREBASE_APPCHECK_DEBUG_TOKEN = <?php echo json_encode($appcheckdebugtoken, $jsflags); ?>;
  <?php endif; ?>
  var appCheck = firebase.appCheck();
  appCheck.activate(
    new firebase.appCheck.ReCaptchaEnterpriseProvider(<?php echo json_encode($recaptchakey, $jsflags); ?>),
    true
  );
  <?php endif; ?>

  var auth = firebase.auth();
  var wantsurl = <?php echo json_encode($wantsurl, $jsflags); ?>;
  var tokenparam = <?php echo json_encode($tokenparam, $jsflags); ?>;
  var loginindexurl = <?php echo json_encode($CFG->wwwroot . '/login/index.php', $jsflags); ?>;

  document.getElementById('firebase-login-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var email = document.getElementById('firebase-email').value;
    var password = document.getElementById('firebase-password').value;
    var errorBox = document.getElementById('firebase-login-error');
    errorBox.style.display = 'none';

    auth.signInWithEmailAndPassword(email, password).then(function(cred) {
      return cred.user.getIdToken();
    }).then(function(idToken) {
      // POST the token to Moodle's own login page rather than wantsurl
      // directly. pre_loginpage_hook() is only ever invoked by
      // require_login() or login/index.php itself - posting straight to
      // wantsurl would silently skip verification whenever wantsurl is a
      // page that doesn't force a login (e.g. the site front page), leaving
      // the user logged out with no error. login/index.php reads wantsurl
      // itself and restores $SESSION->wantsurl, so the final redirect
      // destination is unaffected. Posted, never placed in the URL, so it
      // never lands in the address bar, browser history or access logs.
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = loginindexurl;
      var tokeninput = document.createElement('input');
      tokeninput.type = 'hidden';
      tokeninput.name = tokenparam;
      tokeninput.value = idToken;
      form.appendChild(tokeninput);
      var wantsurlinput = document.createElement('input');
      wantsurlinput.type = 'hidden';
      wantsurlinput.name = 'wantsurl';
      wantsurlinput.value = wantsurl;
      form.appendChild(wantsurlinput);
      document.body.appendChild(form);
      form.submit();
    }).catch(function(error) {
      errorBox.textContent = error.message;
      errorBox.style.display = 'block';
    });
  });
})();
</script>
<?php
echo $OUTPUT->footer();
