<?php defined('SYSPATH') or die('No direct script access.');

// An enhanced version of the standard Controller_Template implementation
class Controller_Azimauth extends Controller_Template {

	protected $user;

	function before()
	{
		parent::before();
		if ($this->auto_render)
		{
			$this->template->title   = '';
			$this->template->content = '';
			$this->template->styles = array();
			$this->template->scripts = array();
		}
		$this->user = Azimauth::instance()->get_user();
	}

	public function after()
	{
		if ($this->auto_render)
		{
			$styles = array(
				'media/css/screen.css' => 'screen, projection',
				'media/css/print.css' => 'print',
				'media/css/style.css' => 'screen',
			);
			$scripts = array(
				'http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js',
			);
			$this->template->styles = array_merge( $this->template->styles, $styles );
			$this->template->scripts = array_merge( $this->template->scripts, $scripts );
		}
		parent::after();
	}

} // End Azimauth
