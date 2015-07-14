<?php 
/*
 Plugin Name: NewsmanApp for Wordpress
 Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 Description: NewsmanApp for Wordpress (sign up widget, subscribers sync, create and send newsletters from blog posts)
 Version: 1.0
 Author: newsmanapp
 Author URI: https://www.newsmanapp.com
 */

/*
 * Basic php template rendering object 
 */
class TemplateFactory{
	/*
	 * The path to the folder containing the php templates
	 * @var string $path
	 */
	protected $path;

	public function __construct($path = false){
		if($path){
			$this->setPath($path);
		}else{
			$this->setPath( dirname(__FILE__) . '/../email_templates/' );
		}
	}
	
	/*
	 * @return string
	 * Returns the path string
	 */
	public function getPath()
	{
		return $this->path;
	}

	/*
	 * Set's the path
	 * @param string $path
	 * @return TemplateFactory object
	 */
	public function setPath($path)
	{
		$this->path = $path;
		return $this;
	}
	
	/*
	 * @param string $template The filename of the template to render
	 * @param $posts The wordpress posts to include in the template
	 * @return string Returns the html of the rendered template
	 */
	public function render( $template, $posts ){
		
		ob_start();		
		require  $this->getPath().$template;
		$html = ob_get_clean();
		
		return $html;				
	}	
}
?>