<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Vion {

	private $ci;

	public $views;
	public $data;
	public $user;

	public function __construct()
	{
		define('VION_VERSION', file_get_contents(FCPATH.'.vion_version'));
		$this->ci = &get_instance();

		$this->ci->config->load('vion');
		$this->ci->load->database();
		$this->ci->load->library('session');
		$this->ci->load->library('parser');
		$class_folder = APPPATH.$this->ci->config->item('class_folder');
		define('VION_CLASS_PATH', $class_folder{-1} == '/' ? $class_folder : $class_folder.'/');
		
		$this->data = array();
		$this->views = array();

		foreach ($this->ci->config->item('constants') as $key => $value) {
			$this->set_data($value, $key);
		}
		
		foreach ($this->ci->config->item('autoload')['libraries'] as $library => $args) {
			if (is_string($args)) {
				$library = $args;
				$args = array();
			}
			$alias = $args['alias'] ?: null;
			if (!$this->ci->load->is_loaded($library)) {
				$this->ci->load->library($library, null, $alias);
			}
			foreach ($args['methods'] as $method => $arguments) {
				$lib = $alias ? strtolower($alias) : strtolower($library);
				call_user_func_array(array($this->ci->$lib, $method), $arguments);
			}
		}

		$this->load_class($this->ci->config->item('autoload')['classes']);
	}
	
	public function view($template = null)
	{
		$this->add_view($this->ci->router->fetch_method(), $this->ci->router->fetch_class())
			->add_template($template)
			->load_user_data()
			->parse_views();
	}

	public function add_view($html, $folder = '')
	{
		if (is_array($html)) {
			foreach ($html as $value) {
				if (is_string($value)) {
					$this->views[] = ($folder ? $folder.'/' : '').$value;
				} else {
					throw new Exception('Array must be made of type string.');
				}
			}
		} else if (is_string($html)) {
			$this->views[] = ($folder ? $folder.'/' : '').$html;
		} else {
			throw new Exception('First argument must be type string or array.');
		}

		return $this;
	}

	public function parse_view($html)
	{
		$this->ci->parser->parse($html, $this->data);

		return $this;
	}

	public function parse_views()
	{
		foreach ($this->views as $html) {
			$this->parse_view($html);
		}

		return $this;
	}

	public function load_user_data()
	{
		$sess_user_key = $this->ci->config->item('session_user_key') ?: 'user';

		if ($this->ci->session->$sess_user_key) {
			$query = $this->ci->db->where($this->ci->config->item('user_db_search_key'), $this->ci->session->user[$this->ci->config->item('user_db_search_key')])->get($this->ci->config->item('user_db_table'));

			if ($query->num_rows()) {
				$this->user = $query->result_array();
				$user_key = $this->ci->config->item('data_user_key') ?: 'user';
				$this->set_data($user_key, $this->user);
			}
		}

		return $this;
	}

	public function add_template($template = null)
	{
		$template_top = array();
		$template_bottom = array();

		$templates = $this->ci->config->item('templates');
		$t = $template ?: $this->ci->config->item('standard_template');
		$temp = $templates[$t];
		$folder = $this->ci->config->item('template_folder').'/';

		foreach ($temp['top'] as $html) {
			$template_top[] = $folder.$html;
		}

		foreach ($temp['bottom'] as $html) {
			$template_bottom[] = $folder.$html;
		}

		$this->views = array_merge($template_top, $this->views, $template_bottom);

		return $this;
	}

	public function set_data($data, string ...$path)
	{
		$curr = &$this->data;

		foreach($path as $key) {
			if ($key) {
				$curr = &$curr[$key];
			} else {
				$curr = &$curr[];
			}
		}

		$curr = $data;

		return $this;
	}

	public function load_class($class)
	{
		if (is_array($class)) {
			foreach ($class as $c) {
				$this->load_class($c);
			}
		} else if (is_string($class)) {
			$class_path = VION_CLASS_PATH.$class.'.php';
			include $class_path;
		}
	}

	public function update()
	{
		$github_version = file_get_contents('https://raw.githubusercontent.com/olivernybo/vion/master/.vion_version?f='.date('Ymdhis'));
		$vion = file_get_contents('https://raw.githubusercontent.com/olivernybo/vion/master/application/libraries/Vion.php?f='.date('Ymdhis'));

		file_put_contents(__FILE__, $vion);
		file_put_contents(FCPATH.'/.vion_version', $github_version);
	}

	public function updates_available()
	{
		$github_version = file_get_contents('https://raw.githubusercontent.com/olivernybo/vion/master/.vion_version?f='.date('Ymdhis'));

		return $github_version === VION_VERSION ? false : $github_version;
	}
}
