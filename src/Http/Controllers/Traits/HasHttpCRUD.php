<?php

namespace EMedia\Oxygen\Http\Controllers\Traits;

use EMedia\Formation\Builder\Formation;
use Illuminate\Http\Request;

trait HasHttpCRUD
{

	protected $dataRepo;
	protected $model;
	protected $entityPlural;
	protected $entitySingular;
	protected $isDestroyingEntityAllowed = false;

	/**
	 *
	 * Return the index route name of the resource
	 *
	 * @return String
	 */
	protected function indexRouteName(): string
	{
		// override this method

		// return 'manage.$resourceName.index';
	}

	/**
	 *
	 * Return the index view name (for the Blade file) for the resource
	 *
	 * @return String
	 */
	protected function indexViewName(): string
	{
		// we're assuming the Blade view file name matches to the route name here.
		// if it's not the same, override this method
		// for example, `return 'manage.my-views-folder.sub-folder.index';`

		if (!$this->indexRouteName()) throw new \InvalidArgumentException("You must call `indexViewName` with the correct view name");

		return $this->indexRouteName();
	}

	/**
	 *
	 * Return the form view name (for the Blade file) for the resource
	 * Override this method to customise the form file
	 *
	 * @return string
	 */
	protected function formViewName(): string
	{
		return 'oxygen::defaults.formation-form';
	}

	/**
	 *
	 * Get the singular name of the entity
	 * Override this method, or set the singularized version explicitly with `$this->entitySingular = 'Some Object';`;
	 *
	 * @return string
	 */
	protected function getEntitySingular(): string
	{
		if ($this->entitySingular) return $this->entitySingular;

		if (!$this->entityPlural) throw new \InvalidArgumentException("`entityPlural` value must be set in the controller");

		return str_singular($this->entityPlural);
	}

	/**
	 *
	 * Index method of the controller
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function index()
	{
		if (empty($this->entityPlural))
			throw new \InvalidArgumentException("'entityPlural' value of the controller is not set.");

		$data = [
			'pageTitle' => $this->entityPlural,
			'allItems' => $this->dataRepo->search(),
			'isDestroyingEntityAllowed' => $this->isDestroyingEntityAllowed,
		];

		$viewName = $this->indexViewName();
		if (empty($viewName)) {
			throw new \InvalidArgumentException("'indexViewName' is empty. Override indexViewName() method in controller.");
		}

		return view($viewName, $data);
	}

	/**
	 *
	 * Create a new record view
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function create()
	{
		if (empty($this->getEntitySingular()))
			throw new \InvalidArgumentException("'entitySingular' value of the controller is not set.");

		$data = [
			'pageTitle' => 'Add new ' . $this->getEntitySingular(),
			'entity' => $this->model,
			'form' => new Formation($this->model),
		];

		$viewName = $this->formViewName();
		if (empty($viewName)) {
			throw new \InvalidArgumentException("'indexViewName' is empty. Override indexViewName() method in controller.");
		}

		return view($viewName, $data);
	}

	/**
	 *
	 * Handle store/POST method for the controller
	 *
	 * @param Request $request
	 *
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function store(Request $request)
	{
		return $this->storeOrUpdateRequest($request);
	}

	/**
	 *
	 * Edit the resource
	 *
	 * @param $id
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function edit($id)
	{
		if (empty($this->getEntitySingular()))
			throw new \InvalidArgumentException("'entityPlural' value of the controller is not set.");

		$entity = $this->dataRepo->find($id);
		$form = new Formation($entity);

		$data = [
			'pageTitle' => 'Edit ' . $this->getEntitySingular(),
			'entity' => $entity,
			'form' => $form,
		];

		$viewName = $this->formViewName();
		if (empty($viewName)) {
			throw new \InvalidArgumentException("'indexViewName' is empty. Override indexViewName() method in controller.");
		}

		return view($viewName, $data);
	}

	/**
	 *
	 * Handle update/PUT request for the controller
	 *
	 * @param Request $request
	 * @param         $id
	 *
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function update(Request $request, $id)
	{
		return $this->storeOrUpdateRequest($request, $id);
	}

	protected function storeOrUpdateRequest(Request $request, $id = null)
	{
		if (empty($this->indexRouteName()))
			throw new \InvalidArgumentException("'indexRouteName()' returns an empty value.");

		if (method_exists($this->model, 'getRules')) {
			$this->validate($request, $this->model->getRules($id), (method_exists($this->model, 'getValidationMessages') ? $this->model->getValidationMessages() : []));
		}

		$entity = $this->dataRepo->fillFromRequest($request, $id);

		return redirect()->route($this->indexRouteName());
	}

	/**
	 *
	 * Handle destroy/DELETE method for the controller
	 *
	 * @param $id
	 *
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function destroy($id)
	{
		// for safety, you must enable access with `$this->isDestroyingEntityAllowed = true;` at the constructor
		// you should also check for the user's valid permissions before doing this

		if ($this->isDestroyingEntityAllowed) {
			$this->dataRepo->delete($id);

			return redirect()->route($this->indexRouteName())->with('success', 'Record deleted.');
		}

		abort(401, 'You are not authorized to access this URL');
	}

}
