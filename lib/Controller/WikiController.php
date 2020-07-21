<?php
namespace OCA\Wiki\Controller;

use OCA\Viewer\Event\LoadViewer;
use OCA\Wiki\Service\WikiCircleService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\QueryException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;

class WikiController extends Controller {
	/** @var WikiCircleService */
	private $service;
	/** @var IEventDispatcher */
	private $eventDispatcher;
	/** @var $string */
	private $userId;

	public function __construct(string $AppName,
								IRequest $request,
								WikiCircleService $service,
								IEventDispatcher $eventDispatcher,
								string $userId
	){
		parent::__construct($AppName, $request);
		$this->service = $service;
		$this->eventDispatcher = $eventDispatcher;
		$this->userId = $userId;
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $path
	 *
	 * @return TemplateResponse
	 */
	public function index(string $path): TemplateResponse {
		$this->eventDispatcher->dispatch(LoadViewer::class, new LoadViewer());
		return new TemplateResponse('wiki', 'main');  // templates/main.php
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * TODO: remove @NoCSRFRequired
	 *
	 * @return DataResponse
	 */
	public function list(): DataResponse {
		return new DataResponse($this->service->getCircles());
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * TODO: remove @NoCSRFRequired
	 *
	 * @param string $name
	 *
	 * @return DataResponse
	 */
	public function create(string $name): DataResponse {
		return new DataResponse($this->service->createWiki($name, $this->userId));
	}
}
