<?php $this->layout('layouts/two-column'); ?>
<?php $this->start('sidebar'); ?>
<nav>Sidebar content</nav>
<?php $this->stop(); ?>
<article><?= $this->e($title) ?></article>
