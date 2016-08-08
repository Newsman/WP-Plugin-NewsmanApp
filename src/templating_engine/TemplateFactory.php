<?php

/*
 * Basic php template rendering object 
 */

class TemplateFactory
{
	/*
	 * The path to the folder containing the php templates
	 * @var string $path
	 */
	protected $path;

	protected $url;

	public function __construct($path = false)
	{
		$upload_dir = wp_upload_dir();
		if ($path)
		{
			$this->setPath($path);
		} else
		{
			$this->setPath(templates_dir);
		}
		$this->url = get_home_url() . template_img_dir;
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
	public function render($template, $posts)
	{
		ob_start();
		$template_dir = $this->url;
		require $this->getPath() . $template;
		$html = ob_get_clean();

		return $html;
	}
}

?>