<h1>User blocked.</h1>
<p>
<?php if (empty($_SESSION['wrong-user'])): ?>
	User
<?php else: ?>
	<?php echo ucfirst($_SESSION['wrong-user']) ?>
<?php endif ?>
 requested to block their profile from being displayed here.</p>
