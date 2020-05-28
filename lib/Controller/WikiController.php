<?php
namespace OCA\Wiki\Controller;

use OCA\Viewer\Event\LoadViewer;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

class WikiController extends Controller {
	/** @var IEventDispatcher */
	private $eventDispatcher;

	public function __construct(string $AppName,
								IRequest $request,
								IEventDispatcher $eventDispatcher
	){
		parent::__construct($AppName, $request);
		$this->eventDispatcher = $eventDispatcher;
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
}
