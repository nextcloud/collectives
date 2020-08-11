<div class="emptycontent">
	<h2>
		<?php
		print_unescaped($l->t('Error: Missing apps'));
		?>
	</h2>
	<h3>
		<?php
		print_unescaped($l->t('The following dependency apps are missing: '));
		?>
	</h3>
	<h3>
		<?php
		$i = 0;
		$len = count($_['appsMissing']);
		foreach ($_['appsMissing'] as $app) {
			print_unescaped('<a href="https://apps.nextcloud.com/apps/' . $app . '">' . $app . '</a>');
			if ($i !== $len - 1) {
				print_unescaped(', ');
			}
			$i++;
		}
		?>
	</h3>
	<h3>
		<?php
		print_unescaped($l->t('Please ask the administrator to enable these apps.'));
		?>
		</p>
</div>

