<?php
/**
 * @class  archiveModel
 * @brief  archive module model class
 */
class ArchiveModel extends Archive
{
	function getFolderList($module_srl, $parent_folder_srl)
	{
		$args = new stdClass;
		$args->module_srl = $module_srl;
		$args->parent_folder_srl = $parent_folder_srl;

		$output = executeQueryArray('archive.getFolderList', $args);
		return (is_array($output->data)) ? $output->data : array();
	}

	function getFolder($folder_srl)
	{
		if (!$folder_srl)
		{
			return null;
		}
		$args = new stdClass;
		$args->folder_srl = $folder_srl;

		$output = executeQuery('archive.getFolder', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}

	/**
	 * @brief Walk up the parent chain to build breadcrumb (root first)
	 */
	function getFolderPath($folder_srl)
	{
		$path = array();
		$guard = 0;
		while ($folder_srl && $guard < 50)
		{
			$folder = $this->getFolder($folder_srl);
			if (!$folder)
			{
				break;
			}
			array_unshift($path, $folder);
			$folder_srl = $folder->parent_folder_srl;
			$guard++;
		}
		return $path;
	}

	function getFileList($module_srl, $folder_srl)
	{
		$args = new stdClass;
		$args->module_srl = $module_srl;
		$args->folder_srl = $folder_srl;

		$output = executeQueryArray('archive.getFileList', $args);
		return (is_array($output->data)) ? $output->data : array();
	}

	function getFile($file_srl)
	{
		$args = new stdClass;
		$args->file_srl = $file_srl;

		$output = executeQuery('archive.getFile', $args);
		return ($output->toBool() && $output->data) ? $output->data : null;
	}
}
/* End of file archive.model.php */
