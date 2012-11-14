<?php if (count($this->users) > 1): ?>
	<div id="switcher">
		<a href="<?php echo $this->urlHelper->url('stats/' . $this->actionName, ['am' => $this->am, 'u' => [end($this->userNames), reset($this->userNames)]]) ?>">
			<i class="icon-switch"></i>
		</a>
	</div>
<?php endif ?>
