<?php
// This plugin's own login page: a self-contained Firebase email/password
// form. Offered as a "Log in with Firebase" option on Moodle's native login
// page via auth_plugin_jwt_sso::loginpage_idp_list(). On success it redirects
// back to $wantsurl with a fresh Firebase ID token attached, which
// pre_loginpage_hook() then verifies as usual.
require_once(__DIR__ . '/../../config.php');

$wantsurl = optional_param('wantsurl', $CFG->wwwroot, PARAM_LOCALURL);

$projectid = get_config('auth_jwt_sso', 'firebase_project_id');
$apikey = get_config('auth_jwt_sso', 'firebase_api_key');
$authdomain = get_config('auth_jwt_sso', 'firebase_auth_domain');
$appid = get_config('auth_jwt_sso', 'firebase_app_id');
$recaptchakey = get_config('auth_jwt_sso', 'recaptcha_enterprise_key');
$appcheckdebugtoken = get_config('auth_jwt_sso', 'appcheck_debug_token');
$tokenparam = get_config('auth_jwt_sso', 'token_param') ?: 'token';

$PAGE->set_url('/auth/jwt_sso/login.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('loginwithfirebase', 'auth_jwt_sso'));

echo $OUTPUT->header();

if (empty($projectid) || empty($apikey)) {
    echo $OUTPUT->notification(get_string('loginpage_missingconfig', 'auth_jwt_sso'), 'error');
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
    apiKey: <?php echo json_encode($apikey); ?>,
    authDomain: <?php echo json_encode($authdomain); ?>,
    projectId: <?php echo json_encode($projectid); ?>,
    appId: <?php echo json_encode($appid); ?>
  });

  <?php if (!empty($recaptchakey)): ?>
  <?php if (!empty($appcheckdebugtoken)): ?>
  // Bypasses reCAPTCHA App Check attestation (e.g. for a test/local domain
  // the reCAPTCHA key isn't registered for). Only takes effect when this
  // setting is explicitly filled in - leave blank in production.
  self.FIREBASE_APPCHECK_DEBUG_TOKEN = <?php echo json_encode($appcheckdebugtoken); ?>;
  <?php endif; ?>
  var appCheck = firebase.appCheck();
  appCheck.activate(
    new firebase.appCheck.ReCaptchaEnterpriseProvider(<?php echo json_encode($recaptchakey); ?>),
    true
  );
  <?php endif; ?>

  var auth = firebase.auth();
  var wantsurl = <?php echo json_encode($wantsurl); ?>;
  var tokenparam = <?php echo json_encode($tokenparam); ?>;

  document.getElementById('firebase-login-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var email = document.getElementById('firebase-email').value;
    var password = document.getElementById('firebase-password').value;
    var errorBox = document.getElementById('firebase-login-error');
    errorBox.style.display = 'none';

    auth.signInWithEmailAndPassword(email, password).then(function(cred) {
      return cred.user.getIdToken();
    }).then(function(idToken) {
      var url = new URL(wantsurl, window.location.origin);
      url.searchParams.set(tokenparam, idToken);
      window.location.href = url.toString();
    }).catch(function(error) {
      errorBox.textContent = error.message;
      errorBox.style.display = 'block';
    });
  });
})();
</script>
<?php
echo $OUTPUT->footer();
