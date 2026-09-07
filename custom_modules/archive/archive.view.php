<?php
/**
 * @class  archiveView
 * @brief  archive module view class (public-facing)
 */
class ArchiveView extends Archive
{
	function init()
	{
	}

	/**
	 * @brief Folder browser (list subfolders + files of the current folder)
	 */
	function dispArchiveIndex()
	{
		$module_srl = $this->module_info->module_srl;
		$folder_srl = (int) Context::get('folder_srl');

		$oArchiveModel = getModel('archive');

		$current_folder = null;
		if ($folder_srl)
		{
			$current_folder = $oArchiveModel->getFolder($folder_srl);
			// Guard against a folder_srl that doesn't belong to this module instance
			if (!$current_folder || (int) $current_folder->module_srl !== (int) $module_srl)
			{
				$folder_srl = 0;
				$current_folder = null;
			}
		}

		$folders = $oArchiveModel->getFolderList($module_srl, $folder_srl);
		$files = $oArchiveModel->getFileList($module_srl, $folder_srl);

		foreach ($files as $file)
		{
			$file->size_label = $this->formatSize($file->filesize);
		}

		$breadcrumb = $folder_srl ? $oArchiveModel->getFolderPath($folder_srl) : array();

		Context::set('cur_folder_srl', $folder_srl);
		Context::set('breadcrumb', $breadcrumb);
		Context::set('folders', $folders);
		Context::set('files', $files);
		Context::set('is_manager', $this->grant->manage ?? false);

		$this->setTemplateFile('list');
	}

	private function formatSize($bytes)
	{
		$bytes = (int) $bytes;
		if ($bytes >= 1048576)
		{
			return round($bytes / 1048576, 1) . ' MB';
		}
		if ($bytes >= 1024)
		{
			return round($bytes / 1024, 1) . ' KB';
		}
		return $bytes . ' B';
	}
}
/* End of file archive.view.php */
