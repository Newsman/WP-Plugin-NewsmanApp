<?php 
/*
 * Basic php template rendering object 
 */
class TemplateFactory{
	/*
	 * The path to the folder containing the php templates
	 * @var string $path
	 */
	protected $path;
	
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
	
	/**
	 * Build and return the full file path (custom or default)
	 */
	private function getFilePath($template)
	{
	    $upload_dir = wp_upload_dir();
	    
	    if(is_file($upload_dir['basedir']."/newsmanapp/email_templates/".$template)){
	        $this->path = $upload_dir['basedir']."/newsmanapp/email_templates/";
	        return $this->path.$template;
	    }else{
	        $this->path = dirname(__DIR__) . "/email_templates/";
	        return $this->path.$template;
	    }
	}
	
	/*
	 * @param string $template The filename of the template to render
	 * @param $posts The wordpress posts to include in the template
	 * @return string Returns the html of the rendered template
	 */
	public function render( $template, $posts )
	{
	    $template = $this->getFilePath($template);
	    
		ob_start();
		
		//useful only for default templates
		$template_dir = plugins_url() . "/newsmanapp/src/email_templates/";	
		
		require  $template;
		$html = ob_get_clean();
		
		return $html;				
	}	
}
?>