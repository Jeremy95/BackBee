<?php

namespace BackBuilder\Renderer;

interface IRendererAdapter
{
	/**
	 * Returns array that contains every single file's extension managed
	 * by this adapter
	 * 
	 * @return array
	 */
	public function getManagedFileExtensions();

	/**
	 * [isValidTemplateFile description]
	 * @param  [type]  $filename    [description]
	 * @param  array   $templateDir [description]
	 * @return boolean              [description]
	 */
	public function isValidTemplateFile($filename, array $templateDir);

	/**
	 * [renderTemplate description]
	 * @param  [type] $filename    [description]
	 * @param  array  $templateDir [description]
	 * @param  array  $params      [description]
	 * @return [type]              [description]
	 */
	public function renderTemplate($filename, array $templateDir, array $params = array());
}