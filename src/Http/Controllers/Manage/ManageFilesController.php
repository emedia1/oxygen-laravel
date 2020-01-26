<?php


namespace EMedia\Oxygen\Http\Controllers\Manage;

use App\Entities\Files\File;
use App\Entities\Files\FilesRepository;
use App\Http\Controllers\Controller;
use EMedia\FileControl\PathResolver\PathResolver;
use EMedia\FileControl\Uploader\FileUploader;
use EMedia\Formation\Builder\Formation;
use EMedia\QuickData\Entities\Search\SearchFilter;
use Illuminate\Http\Request;
use Spatie\Html\Elements\Form;
use Webpatser\Uuid\Uuid;

class ManageFilesController extends Controller
{

	protected $dataRepo;

	protected $uploadDisk;

	public function __construct(FilesRepository $dataRepo, File $model)
	{
		$this->model        = $model;
		$this->dataRepo     = $dataRepo;

		// $this->middleware('auth.acl:permissions[manage-file-uploads]')->except('show');
	}

	/**
	 *
	 * Show all files
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function index()
	{
		$filter = new SearchFilter();
		$filter->orderBy(['created_at' => 'asc']);

		return view('oxygen::manage.files.index', [
			'pageTitle' => 'Manage Files',
			'allItems' => $this->dataRepo->search([], $filter),
		]);
	}

	/**
	 *
	 * Show new file form
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function create()
	{
		$file = new File();

		return view('oxygen::manage.files.form', [
			'pageTitle' => 'Add a New File',
			'entity' => $file,
			'form' => new Formation($file),
			'selectedKey' => 'other',
			'fileKeys' => File::fileKeys(),
		]);
	}

	public function edit(File $file)
	{
		return view('oxygen::manage.files.form', [
			'pageTitle' => 'Edit File',
			'entity' => $file,
			'selectedKey' => ($file->key)? $file->key: 'other',
			'form' => new Formation($file),
			'fileKeys' => File::fileKeys(),
		]);
	}

	public function update(Request $request)
	{
		$file = $this->dataRepo->find($request->id);
		if (empty($file)) return back()->with('erro', 'Invalid file.');

		$this->validate($request, [
			'file' => 'required|file',
//			'key' => 'required|unique:files,key,' . $file->id,
			'id' => 'required',
		]);


		$originalFilePath = $this->resolvePathFromFile($file);

		$fh = new FileUploader($request);
		$fh->toDisk($this->uploadDisk)
		   ->saveToDir('files');

		$result = $fh->upload();

		if ($result->isSuccessful()) {
			$fileKey = $request->key;
			if ($fileKey === 'other') $fileKey = null;

			if (empty($fileKey) && !empty($request->custom_key)) {
				$fileKey = snake_case($request->custom_key);
			}

			$file->fill([
				// 'name' => File::fileKeys($request->key),
				'original_filename' => $result->getOriginalFilename(),
				'file_path' => $result->filePath(),
				'file_disk' => $result->diskName(),
				'file_url'  => $result->publicUrl(),
				'allow_public_access' => empty($request->allow_public_access)? false: true,
				'file_size_bytes' => $result->getFileSize(),
				'uploaded_by_user_id' => (auth()->id()) ?? auth()->id()
			]);
			$file->save();

			if (file_exists($originalFilePath)) {
				unlink($originalFilePath);
			}

			return redirect()->route('manage.files.index')->with('success', 'File uploaded.');
		}

		return back()->with('error', 'Failed to upload file')->withInput(request()->only('key'));
	}

	/**
	 *
	 * Save a file
	 *
	 * @param Request $request
	 *
	 * @return \Illuminate\Http\RedirectResponse
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function store(Request $request)
	{
		$this->validate($request, [
			'file' => 'required|file',
			// 'key' => 'required|unique:files,key',
			'key' => 'required',
		]);

		$fh = new FileUploader($request);
		$fh->toDisk($this->uploadDisk)
		   ->saveToDir('files');

		$result = $fh->upload();

		if ($result->isSuccessful()) {
			$fileKey = $request->key;
			if ($fileKey === 'other') $fileKey = null;

			// make sure the key is valid
			if (empty($fileKey)) {
				if (empty($request->custom_key)) {
					$fileKey = Uuid::generate(4);
				} else {
					$fileKey = snake_case($request->custom_key);
				}
			}

			$file = $this->dataRepo->findByKey($fileKey);

			if ($file) {
				return back()->with('error', "A file with a key `{$fileKey}` already exists. Add a unique custom key, or leave the key field empty to auto-generate a unique key.");
			}

			$file = new File([
				'name' => File::fileKeys($request->key),
				'key' => $fileKey,
				'allow_public_access' => empty($request->allow_public_access)? false: true,
				'original_filename' => $result->getOriginalFilename(),
				'file_path' => $result->filePath(),
				'file_disk' => $result->diskName(),
				'file_url'  => $result->publicUrl(),
				'file_size_bytes' => $result->getFileSize(),
				'uploaded_by_user_id' => (auth()->id()) ?? auth()->id()
			]);
			$file->category = 'admin_uploads';
			$file->save();

			return redirect()->route('manage.files.index')->with('success', 'File uploaded.');
		}

		return back()->with('error', 'Failed to upload file')->withInput(request()->only('key'));
	}

	/**
	 *
	 * Download a file
	 *
	 * @param $uuid
	 *
	 * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
	 * @throws \EMedia\FileControl\Exceptions\FailedToResolvePathException
	 */
	public function download($uuid)
	{
		$file = $this->dataRepo->findByUuid($uuid);

		$filePath = $this->resolvePathFromFile($file);

		return response()->download($filePath, $file->original_filename);
	}

	/**
	 *
	 * Show a file on browser
	 *
	 * @param $uuid
	 *
	 * @return mixed
	 * @throws \EMedia\FileControl\Exceptions\FailedToResolvePathException
	 */
	public function show($uuid)
	{
		$file = $this->dataRepo->findByUuid($uuid);

		if (!$file) return back()->with('error', 'Invalid file request.');

		$filePath = $this->resolvePathFromFile($file);

		return response()->file($filePath);
	}

	/**
	 *
	 * Delete a file
	 *
	 * @param File $file
	 *
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function destroy(File $file)
	{
		if (!$file->isDeleteAllowed()) {
			return redirect()->route('oxygen::manage.files.index')->with('error', 'This file is locked and cannot be deleted.');
		}

		$filePath = $this->resolvePathFromFile($file);

		if (file_exists($filePath)) {
			unlink($filePath);
		}

		File::destroy($file->id);

		return redirect()->route('manage.files.index')->with('success', 'File deleted.');
	}

	/**
	 * @param File $file
	 *
	 * @return string
	 * @throws \EMedia\FileControl\Exceptions\FailedToResolvePathException
	 */
	protected function resolvePathFromFile(File $file): string
	{
		return PathResolver::resolvePath($file->file_disk, $file->file_path);
	}

}
