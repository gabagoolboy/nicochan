<?php
 require 'inc/bootstrap.php';
 checkBan();

 die(
    Element('page.html', array(
      'title' => _('Não está banida!'),
      'config' => $config,
      'nojavascript' => true,
      'boardlist' => createBoardlist(FALSE),
      'body' => Element('notbanned.html', array(
	      'text_body' => 'Parabéns por ser uma boa anã!',
	      'text_h2' => 'Você não está banida!',
		)
	))
	));


?>
