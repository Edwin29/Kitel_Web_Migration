<?php
/**
 * @class  archiveController
 * @brief  archive module controller class (public-facing actions)
 */
class ArchiveController extends Archive
{
	function init()
	{
	}

	private function getStorageDir()
	{
		return './files/attach/archive/' . $this->module_info->module_srl . '/';
	}

	/**
	 * @brief Create a new folder under the current folder
	 */
	function procArchiveInsertFolder()
	{
		$name = trim(Context::get('name'));
		$parent_folder_srl = (int) Context::get('folder_srl');

		if ($name === '')
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}

		$logged_info = Context::get('logged_info');

		$args = new stdClass;
		$args->folder_srl = getNextSequence();
		$args->module_srl = $this->module_info->module_srl;
		$args->parent_folder_srl = $parent_folder_srl;
		$args->name = $name;
		$args->member_srl = $logged_info->member_srl;

		$output = executeQuery('archive.insertFolder', $args);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispArchiveIndex', 'mid', Context::get('mid'), 'folder_srl', $parent_folder_srl));
	}

	/**
	 * @brief Delete a folder and everything under it (subfolders, files, and the physical files on disk)
	 */
	function procArchiveDeleteFolder()
	{
		$folder_srl = (int) Context::get('folder_srl');
		$oArchiveModel = getModel('archive');
		$folder = $oArchiveModel->getFolder($folder_srl);
		if (!$folder || (int) $folder->module_srl !== (int) $this->module_info->module_srl)
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}

		$this->deleteFolderRecursive($folder_srl);

		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispArchiveIndex', 'mid', Context::get('mid'), 'folder_srl', $folder->parent_folder_srl));
	}

	private function deleteFolderRecursive($folder_srl)
	{
		$oArchiveModel = getModel('archive');

		// Delete files directly inside this folder
		$files = $oArchiveModel->getFileList($this->module_info->module_srl, $folder_srl);
		foreach ($files as $file)
		{
			$this->deleteFileEntry($file);
		}

		// Recurse into subfolders
		$subfolders = $oArchiveModel->getFolderList($this->module_info->module_srl, $folder_srl);
		foreach ($subfolders as $sub)
		{
			$this->deleteFolderRecursive($sub->folder_srl);
		}

		$args = new stdClass;
		$args->folder_srl = $folder_srl;
		executeQuery('archive.deleteFolder', $args);
	}

	/**
	 * @brief Upload a file into the current folder
	 */
	function procArchiveUploadFile()
	{
		$folder_srl = (int) Context::get('folder_srl');
		$file_info = Context::get('Filedata');

		if (!$file_info || !is_uploaded_file($file_info['tmp_name']))
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}

		$logged_info = Context::get('logged_info');
		$file_srl = getNextSequence();

		$safe_name = preg_replace('/[^a-zA-Z0-9._\-가-힣]/u', '_', $file_info['name']);
		$stored_filename = $file_srl . '_' . $safe_name;

		$storage_dir = $this->getStorageDir();
		FileHandler::makeDir($storage_dir);

		$target = FileHandler::getRealPath($storage_dir . $stored_filename);
		if (!move_uploaded_file($file_info['tmp_name'], $target))
		{
			throw new Rhymix\Framework\Exception('msg_file_upload_error');
		}

		$args = new stdClass;
		$args->file_srl = $file_srl;
		$args->module_srl = $this->module_info->module_srl;
		$args->folder_srl = $folder_srl;
		$args->source_filename = $file_info['name'];
		$args->stored_filename = $stored_filename;
		$args->filesize = $file_info['size'];
		$args->member_srl = $logged_info->member_srl;

		$output = executeQuery('archive.insertFile', $args);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispArchiveIndex', 'mid', Context::get('mid'), 'folder_srl', $folder_srl));
	}

	/**
	 * @brief Delete a single file (DB row + physical file)
	 */
	function procArchiveDeleteFile()
	{
		$file_srl = (int) Context::get('file_srl');
		$oArchiveModel = getModel('archive');
		$file = $oArchiveModel->getFile($file_srl);
		if (!$file || (int) $file->module_srl !== (int) $this->module_info->module_srl)
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}

		$folder_srl = $file->folder_srl;
		$this->deleteFileEntry($file);

		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispArchiveIndex', 'mid', Context::get('mid'), 'folder_srl', $folder_srl));
	}

	private function deleteFileEntry($file)
	{
		$path = FileHandler::getRealPath($this->getStorageDir() . $file->stored_filename);
		if (Rhymix\Framework\Storage::isFile($path))
		{
			Rhymix\Framework\Storage::delete($path);
		}

		$args = new stdClass;
		$args->file_srl = $file->file_srl;
		executeQuery('archive.deleteFile', $args);
	}

	/**
	 * @brief Stream a file for download (grants already restrict this to permission="list")
	 */
	function procArchiveDownloadFile()
	{
		$file_srl = (int) Context::get('file_srl');
		$oArchiveModel = getModel('archive');
		$file = $oArchiveModel->getFile($file_srl);
		if (!$file || (int) $file->module_srl !== (int) $this->module_info->module_srl)
		{
			throw new Rhymix\Framework\Exceptions\InvalidRequest;
		}

		$path = FileHandler::getRealPath($this->getStorageDir() . $file->stored_filename);
		if (!Rhymix\Framework\Storage::isFile($path))
		{
			throw new Rhymix\Framework\Exceptions\TargetNotFound;
		}

		$args = new stdClass;
		$args->file_srl = $file_srl;
		$args->download_count = (int) $file->download_count + 1;
		executeQuery('archive.updateFileDownloadCount', $args);

		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . rawurlencode($file->source_filename) . '"');
		header('Content-Length: ' . filesize($path));
		header('Pragma: public');
		readfile($path);
		exit;
	}
}
/* End of file archive.controller.php */
