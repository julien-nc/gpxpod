<?php
$appId = OCA\Gpxpod\AppInfo\Application::APP_ID;
\OCP\Util::addStyle($appId, 'sharePassword');
?>

<div id="app">
	<div id="app-content">
		<form id="password-form" method="POST" action="<?php p($_['action']); ?>">
			<h2><?php p($l->t('Password protected share')); ?></h2>

			<?php
			if ($_['wrong']) {
				echo '<p id="wrongcredentials">';
				p($l->t('Wrong link password'));
				echo '</p>';
			}
?>

			<br/>
			<label for="passwordInput" id="passwordlabel"><?php p($l->t('Share link password')); ?></label>
			<br/>
			<input id="passwordInput" name="password" type="password" />

			<br/>
			<button id="okbutton" type="submit">Submit</button>

		</form>
	</div>
</div>
