<?php

declare(strict_types=1);

namespace OCA\MediaFrames\Controller;

use Exception;
use OCA\MediaFrames\AppInfo\Application;
use OCA\MediaFrames\Db\EntryMapper;
use OCA\MediaFrames\Db\Frame;
use OCA\MediaFrames\Db\FrameFile;
use OCA\MediaFrames\Db\FrameMapper;
use OCA\MediaFrames\Service\MediaFrameService;
use OCA\MediaFrames\Service\FramePresenterService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Common\Exception\NotFoundException;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IPreview;
use OCP\IUser;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\Security\Bruteforce\IThrottler;
use OCP\App\IAppManager;
use OCP\Util;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller
{
  private const BRUTEFORCE_ACTION = 'media_frames';

  private EntryMapper $entryMapper;
  private FrameMapper $frameMapper;
  private IThrottler $throttler;
  private IRootFolder $rootFolder;
  private IPreview $preview;
  private IConfig $config;
  private ?IUser $currentUser;
  private IURLGenerator $urlGenerator;
  private IDBConnection $db;
  private IAppManager $appManager;
  private FramePresenterService $framePresenter;

  public function __construct(
    $appName,
    IRequest $request,
    EntryMapper $entryMapper,
    FrameMapper $frameMapper,
    IThrottler $throttler,
    IRootFolder $rootFolder,
    IPreview $preview,
    IConfig $config,
    IUserSession $userSession,
    IURLGenerator $urlGenerator,
    IDBConnection $db,
    IAppManager $appManager,
    FramePresenterService $framePresenter,
  ) {
    parent::__construct($appName, $request);
    $this->entryMapper = $entryMapper;
    $this->frameMapper = $frameMapper;
    $this->throttler = $throttler;
    $this->rootFolder = $rootFolder;
    $this->preview = $preview;
    $this->config = $config;
    $this->currentUser = $userSession->getUser();
    $this->urlGenerator = $urlGenerator;
    $this->db = $db;
    $this->appManager = $appManager;
    $this->framePresenter = $framePresenter;
  }

  // ── Authenticated (admin) routes ────────────────────────────────────────────

  #[NoCSRFRequired]
  #[NoAdminRequired]
  #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
  #[FrontpageRoute(verb: 'GET', url: '/')]
  public function index(): Response
  {
    Util::addStyle(Application::APP_ID, 'main');
    Util::addScript(Application::APP_ID, 'vendor/qrcode.min');

    try {
      $uid = $this->currentUser->getUID();
      $frames = $this->frameMapper->getAllByUser($uid);
      $response = $this->renderPage('IndexPage', [
        'frames' => $this->framePresenter->presentFrames($frames),
        'urls' => [
          'new' => $this->urlGenerator->linkToRoute('media_frames.page.new'),
        ],
      ]);
      $response->getContentSecurityPolicy()->addAllowedFrameDomain($this->request->getServerHost());
      return $response;
    } catch (Exception $error) {
      return $this->errorPage($error);
    }
  }

  #[NoCSRFRequired]
  #[NoAdminRequired]
  #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
  #[FrontpageRoute(verb: 'GET', url: '/new')]
  public function new(): Response
  {
    try {
      $uid = $this->currentUser->getUID();
      Util::addStyle(Application::APP_ID, 'main');
      Util::addStyle(Application::APP_ID, 'highlight-theme');
      Util::addStyle(Application::APP_ID, 'vendor/code-input.min');
      Util::addScript(Application::APP_ID, 'vendor/highlight.min');
      Util::addScript(Application::APP_ID, 'vendor/highlight-javascript.min');
      Util::addScript(Application::APP_ID, 'vendor/code-input.min');

      return $this->renderPage('NewPage', [
        'frame' => new Frame(),
        'albums' => $this->frameMapper->getAvailableAlbums($uid),
        'requestToken' => Util::callRegister(),
        'urls' => [
          'index' => $this->urlGenerator->linkToRoute('media_frames.page.index'),
          'create' => $this->urlGenerator->linkToRoute('media_frames.page.create'),
          'folders' => $this->urlGenerator->linkToRoute('media_frames.page.folders'),
        ],
      ]);
    } catch (Exception $error) {
      return $this->errorPage($error);
    }
  }

  #[NoAdminRequired]
  #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
  #[FrontpageRoute(verb: 'POST', url: '/')]
  public function create(): Response
  {
    try {
      $params = $this->request->getParams();
      $uid = $this->currentUser->getUID();

      $this->frameMapper->createFrame(
        $params['name'],
        $uid,
        $this->buildSources($params),
        $params['selectionMethod'],
        (bool) ($params['favorNewAdditions'] ?? false),
        $params['rotationUnit'],
        (int) $params['rotationsPerUnit'],
        $params['startDayAt'],
        $params['endDayAt'],
        (int) ($params['imageDurationSeconds'] ?? 30),
        $params['videoDuration'] ?? 'full',
        (int) ($params['videoFixedDurationSeconds'] ?? 30),
        $this->hashPassword($params['devicePassword'] ?? ''),
        (bool) ($params['showPhotoTimestamp'] ?? true),
        (bool) ($params['showPhotoPlace'] ?? false),
        (bool) ($params['showClock'] ?? false),
        $params['photoSize'] ?? 'smart-fit',
        $params['backgroundType'] ?? 'aura',
        $params['backgroundColor'] ?? '#000000',
        $params['javascript'] ?? ''
      );

      return new RedirectResponse($this->urlGenerator->linkToRoute('media_frames.page.index'));
    } catch (Exception $error) {
      return $this->errorPage($error);
    }
  }

  #[NoCSRFRequired]
  #[NoAdminRequired]
  #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
  #[FrontpageRoute(verb: 'GET', url: '/{id}/edit', requirements: ['id' => '[0-9]+'])]
  public function edit($id): Response
  {
    try {
      $uid = $this->currentUser->getUID();
      Util::addStyle(Application::APP_ID, 'main');
      Util::addStyle(Application::APP_ID, 'highlight-theme');
      Util::addStyle(Application::APP_ID, 'vendor/code-input.min');
      Util::addScript(Application::APP_ID, 'vendor/highlight.min');
      Util::addScript(Application::APP_ID, 'vendor/highlight-javascript.min');
      Util::addScript(Application::APP_ID, 'vendor/code-input.min');

      $frame = $this->frameMapper->getByUserIdAndFrameId($uid, (int) $id);

      return $this->renderPage('EditPage', [
        'frame' => $this->framePresenter->presentFrame($frame),
        'albums' => $this->frameMapper->getAvailableAlbums($uid),
        'requestToken' => Util::callRegister(),
        'urls' => [
          'index' => $this->urlGenerator->linkToRoute('media_frames.page.index'),
          'folders' => $this->urlGenerator->linkToRoute('media_frames.page.folders'),
        ],
      ]);
    } catch (Exception $error) {
      return $this->errorPage($error);
    }
  }

  #[NoAdminRequired]
  #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
  #[FrontpageRoute(verb: 'POST', url: '/{id}', requirements: ['id' => '[0-9]+'])]
  public function update($id): Response
  {
    try {
      $uid = $this->currentUser->getUID();
      $frame = $this->frameMapper->getByUserIdAndFrameId($uid, (int) $id);
      $params = $this->request->getParams();

      // Keep existing password if new one is empty
      $passwordHash = null;
      $newPassword = $params['devicePassword'] ?? '';
      if ($newPassword !== '') {
        $passwordHash = $this->hashPassword($newPassword);
      } elseif (($params['clearDevicePassword'] ?? '') === '1') {
        $frame->setDevicePasswordHash(null);
      }

      $this->frameMapper->updateFrame(
        $frame,
        $params['name'],
        $this->buildSources($params),
        $params['selectionMethod'],
        (bool) ($params['favorNewAdditions'] ?? false),
        $params['rotationUnit'],
        (int) $params['rotationsPerUnit'],
        $params['startDayAt'],
        $params['endDayAt'],
        (int) ($params['imageDurationSeconds'] ?? 30),
        $params['videoDuration'] ?? 'full',
        (int) ($params['videoFixedDurationSeconds'] ?? 30),
        $passwordHash,
        (bool) ($params['showPhotoTimestamp'] ?? true),
        (bool) ($params['showPhotoPlace'] ?? false),
        (bool) ($params['showClock'] ?? false),
        $params['photoSize'] ?? 'smart-fit',
        $params['backgroundType'] ?? 'aura',
        $params['backgroundColor'] ?? '#000000',
        $params['javascript'] ?? ''
      );

      return new RedirectResponse($this->urlGenerator->linkToRoute('media_frames.page.index'));
    } catch (Exception $error) {
      return $this->errorPage($error);
    }
  }

  #[NoCSRFRequired]
  #[NoAdminRequired]
  #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
  #[FrontpageRoute(verb: 'DELETE', url: '/{id}', requirements: ['id' => '[0-9]+'])]
  public function destroy($id): Response
  {
    try {
      $uid = $this->currentUser->getUID();
      $frame = $this->frameMapper->getByUserIdAndFrameId($uid, (int) $id);
      $this->frameMapper->destroyFrame($frame);
      return new Response(204);
    } catch (Exception $error) {
      return $this->errorPage($error);
    }
  }

  #[NoCSRFRequired]
  #[NoAdminRequired]
  #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
  #[FrontpageRoute(verb: 'GET', url: '/api/folders')]
  public function folders(): Response
  {
    try {
      $uid = $this->currentUser->getUID();
      $path = $this->request->getParam('path', '/');
      $userFolder = $this->rootFolder->getUserFolder($uid);

      $node = $path === '/' ? $userFolder : $userFolder->get($path);

      if (!($node instanceof Folder)) {
        return new JSONResponse(['folders' => []]);
      }

      $folders = [];
      foreach ($node->getDirectoryListing() as $child) {
        if ($child instanceof Folder) {
          $folders[] = [
            'name' => $child->getName(),
            'path' => $path === '/' ? '/' . $child->getName() : $path . '/' . $child->getName(),
          ];
        }
      }

      return new JSONResponse(['folders' => $folders]);
    } catch (Exception) {
      return new JSONResponse(['folders' => []]);
    }
  }

  // ── Public frame routes ──────────────────────────────────────────────────────

  #[NoCSRFRequired]
  #[PublicPage]
  #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
  #[FrontpageRoute(verb: 'GET', url: '/{shareToken}', requirements: ['shareToken' => '[a-zA-Z0-9]{64}'])]
  public function mediaframe($shareToken): Response
  {
    $frame = $this->getFrameOrThrottle($shareToken);
    if (!$frame) {
      throw new NotFoundException('Frame not found');
    }

    // Device auth check
    $authResult = $this->checkDeviceAuth($frame, $shareToken);
    if ($authResult !== null) {
      return $authResult;
    }

    try {
      $mediaUrl = $this->urlGenerator->linkToRoute(
        'media_frames.page.mediaframeMedia',
        ['shareToken' => $frame->getShareToken()]
      );

      $response = $this->renderPage(
        'FramePage',
        [
          'showPhotoTimestamp' => $frame->getShowPhotoTimestamp(),
          'showPhotoPlace' => $frame->getShowPhotoPlace(),
          'showClock' => $frame->getShowClock(),
          'photoSize' => $frame->getPhotoSize(),
          'backgroundType' => $frame->getBackgroundType(),
          'backgroundColor' => $frame->getBackgroundColor(),
          'mediaUrl' => $mediaUrl,
          'shareToken' => $shareToken,
          'appPath' => $this->appManager->getAppWebPath(Application::APP_ID),
        ],
        true,
        $frame->getBackgroundType() === 'color' ? $frame->getBackgroundColor() : '#000',
        $frame->getJavascript(),
      );

      $response->getContentSecurityPolicy()->addAllowedFrameAncestorDomain('*');
      return $response;
    } catch (Exception $error) {
      return $this->errorPage($error);
    }
  }

  #[NoCSRFRequired]
  #[PublicPage]
  #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
  #[FrontpageRoute(verb: 'GET', url: '/{shareToken}/auth', requirements: ['shareToken' => '[a-zA-Z0-9]{64}'])]
  public function mediaframeAuth($shareToken): Response
  {
    $frame = $this->getFrameOrThrottle($shareToken);
    if (!$frame) {
      throw new NotFoundException('Frame not found');
    }

    // Check if a ?key= was supplied in the URL for headless setup (e.g., Raspberry Pi)
    $key = $this->request->getParam('key', '');
    if ($key !== '' && $frame->getDevicePasswordHash() !== null) {
      if (password_verify($key, $frame->getDevicePasswordHash())) {
        $response = new RedirectResponse(
          $this->urlGenerator->linkToRoute('media_frames.page.mediaframe', ['shareToken' => $shareToken])
        );
        $this->setDeviceCookie($response, $frame->getId(), $shareToken);
        return $response;
      }
      $this->throttler->registerAttempt(self::BRUTEFORCE_ACTION, $this->request->getRemoteAddress());
    }

    return $this->renderAuthPage($shareToken, false);
  }

  #[PublicPage]
  #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
  #[FrontpageRoute(verb: 'POST', url: '/{shareToken}/auth', requirements: ['shareToken' => '[a-zA-Z0-9]{64}'])]
  public function mediaframeAuthSubmit($shareToken): Response
  {
    $frame = $this->getFrameOrThrottle($shareToken);
    if (!$frame) {
      throw new NotFoundException('Frame not found');
    }

    $password = $this->request->getParam('password', '');

    if (
      $frame->getDevicePasswordHash() !== null &&
      password_verify($password, $frame->getDevicePasswordHash())
    ) {
      $response = new RedirectResponse(
        $this->urlGenerator->linkToRoute('media_frames.page.mediaframe', ['shareToken' => $shareToken])
      );
      $this->setDeviceCookie($response, $frame->getId(), $shareToken);
      return $response;
    }

    $this->throttler->registerAttempt(self::BRUTEFORCE_ACTION, $this->request->getRemoteAddress());
    return $this->renderAuthPage($shareToken, true);
  }

  #[NoCSRFRequired]
  #[PublicPage]
  #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
  #[FrontpageRoute(verb: 'GET', url: '/{shareToken}/media', requirements: ['shareToken' => '[a-zA-Z0-9]+'])]
  #[FrontpageRoute(verb: 'HEAD', url: '/{shareToken}/media', requirements: ['shareToken' => '[a-zA-Z0-9]+'])]
  public function mediaframeMedia($shareToken): Response
  {
    $frame = $this->getFrameOrThrottle($shareToken);
    if (!$frame) {
      throw new NotFoundException('Frame not found');
    }

    $authResult = $this->checkDeviceAuth($frame, $shareToken);
    if ($authResult !== null) {
      return new Response(401);
    }

    try {
      $service = new MediaFrameService($this->entryMapper, $this->frameMapper, $this->rootFolder, $frame);
      $frameFile = $service->getCurrentFrameFile();

      if (!$frameFile) {
        return new NotFoundResponse();
      }

      $mediaType = $frameFile->getMediaType();
      $node = $service->getFrameFileNode($frameFile);

      $baseHeaders = [
        'X-Media-Type' => $mediaType,
        'X-File-Id' => (string) $frameFile->getFileId(),
        'X-Photo-Timestamp' => (string) $frameFile->getCapturedAtTimestamp(),
        'X-Photo-Place' => rawurlencode($frameFile->getPlace() ?? ''),
        'Expires' => $frameFile->getExpiresHeader(),
        'Cache-Control' => 'no-cache',
      ];

      if ($this->request->getMethod() === 'HEAD') {
        return new Response(200, $baseHeaders);
      }

      if ($mediaType === FrameFile::MEDIA_TYPE_VIDEO) {
        // Stream video directly
        $data = $node->getContent();
        return new DataDisplayResponse($data, 200, array_merge($baseHeaders, [
          'Content-Type' => $frameFile->getMimeType(),
          'X-Duration' => $frame->getVideoDuration() === 'full' ? 'full' : (string) $frame->getVideoFixedDurationSeconds(),
        ]));
      }

      // Image or document: return a JPEG preview
      $previewFile = $this->preview->getPreview($node, 1920, 1920);
      return new \OCP\AppFramework\Http\FileDisplayResponse($previewFile, 200, array_merge($baseHeaders, [
        'Content-Type' => 'image/jpeg',
      ]));
    } catch (Exception $error) {
      return $this->errorPage($error);
    }
  }

  #[NoCSRFRequired]
  #[PublicPage]
  #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
  #[FrontpageRoute(verb: 'GET', url: '/{shareToken}/file/{fileId}', requirements: ['shareToken' => '[a-zA-Z0-9]+', 'fileId' => '[0-9]+'])]
  public function mediaframeFile($shareToken, $fileId): Response
  {
    $frame = $this->getFrameOrThrottle($shareToken);
    if (!$frame) {
      throw new NotFoundException('Frame not found');
    }

    $authResult = $this->checkDeviceAuth($frame, $shareToken);
    if ($authResult !== null) {
      return new Response(401);
    }

    try {
      $service = new MediaFrameService($this->entryMapper, $this->frameMapper, $this->rootFolder, $frame);
      $frameFile = $this->frameMapper->getFrameFileById($frame, (int) $fileId);

      if (!$frameFile) {
        return new NotFoundResponse();
      }

      $node = $service->getFrameFileNode($frameFile);
      $data = $node->getContent();

      return new DataDisplayResponse($data, 200, [
        'Content-Type' => $frameFile->getMimeType(),
        'Cache-Control' => 'private, max-age=3600',
      ]);
    } catch (Exception) {
      return new NotFoundResponse();
    }
  }

  // ── Helpers ─────────────────────────────────────────────────────────────────

  private function getFrameOrThrottle(string $shareToken): ?Frame
  {
    $frame = $this->frameMapper->getByShareToken($shareToken);
    if (!$frame) {
      $this->throttler->registerAttempt(self::BRUTEFORCE_ACTION, $this->request->getRemoteAddress());
    }
    return $frame;
  }

  private function checkDeviceAuth(Frame $frame, string $shareToken): ?Response
  {
    if ($frame->getDevicePasswordHash() === null) {
      return null; // No protection
    }

    $cookieName = 'mf_device_' . $frame->getId();
    $expectedValue = $this->getExpectedCookieValue($frame->getId(), $shareToken);
    $cookieValue = $this->request->getCookie($cookieName);

    if ($cookieValue !== null && hash_equals($expectedValue, $cookieValue)) {
      return null; // Valid cookie
    }

    // Redirect to auth page
    return new RedirectResponse(
      $this->urlGenerator->linkToRoute('media_frames.page.mediaframeAuth', ['shareToken' => $shareToken])
    );
  }

  private function getExpectedCookieValue(int $frameId, string $shareToken): string
  {
    $secret = $this->config->getSystemValue('secret', '');
    return hash_hmac('sha256', $frameId . ':' . $shareToken, $secret);
  }

  private function setDeviceCookie(Response $response, int $frameId, string $shareToken): void
  {
    $cookieName = 'mf_device_' . $frameId;
    $value = $this->getExpectedCookieValue($frameId, $shareToken);
    $response->addCookie($cookieName, $value, new \DateTime('+1 year'), '/', null, true, true);
  }

  private function hashPassword(string $password): ?string
  {
    if ($password === '') {
      return null;
    }
    return password_hash($password, PASSWORD_BCRYPT);
  }

  private function buildSources(array $params): string
  {
    // The form sends sources as a JSON string
    $sourcesJson = $params['sources'] ?? '[]';
    $sources = json_decode($sourcesJson, true);
    if (!is_array($sources)) {
      $sources = [];
    }
    return json_encode($sources);
  }

  private function renderPage(string $name, array $props, bool $blank = false, string $backgroundColor = '#000', string $javascript = ''): TemplateResponse
  {
    return new TemplateResponse(
      appName: Application::APP_ID,
      templateName: $blank ? 'blank' : 'page',
      params: [
        'pageName' => $name,
        'pageProps' => $props,
        'javascript' => $blank ? $javascript : null,
        'backgroundColor' => $backgroundColor,
        'appPath' => $this->appManager->getAppWebPath(Application::APP_ID),
      ],
      renderAs: $blank ? TemplateResponse::RENDER_AS_BLANK : TemplateResponse::RENDER_AS_USER,
    );
  }

  private function renderAuthPage(string $shareToken, bool $failed): Response
  {
    $response = new TemplateResponse(
      appName: Application::APP_ID,
      templateName: 'auth',
      params: [
        'shareToken' => $shareToken,
        'failed' => $failed,
        'authUrl' => $this->urlGenerator->linkToRoute('media_frames.page.mediaframeAuthSubmit', ['shareToken' => $shareToken]),
        'appPath' => $this->appManager->getAppWebPath(Application::APP_ID),
      ],
      renderAs: TemplateResponse::RENDER_AS_BLANK,
    );
    return $response;
  }

  private function errorPage(Exception $error): Response
  {
    Util::addStyle(Application::APP_ID, 'main');

    $debugInfo = [
      ['**Nextcloud version**', implode('.', Util::getVersion())],
      ['**Media Frames version**', $this->appManager->getAppVersion(Application::APP_ID)],
      ['**Database**', $this->db->getDatabaseProvider()],
      ['**Error**', $error->getMessage()],
      ['**File:line**', '`' . $error->getFile() . ':' . $error->getLine() . '`'],
      ['**Stack trace**', "```txt\n" . $error->getTraceAsString() . "\n```"],
    ];

    $debugInfoString = implode("\n\n", array_map(fn($v) => implode("\n", $v), $debugInfo));
    $issueBody = "## What happened\n\n[Describe what you did]\n\n## Debug info\n\n" . $debugInfoString;
    $reportLink = 'https://github.com/Rolleps/nextcloud-media-frames/issues/new?title='
      . urlencode($error->getMessage()) . '&body=' . urlencode($issueBody);

    $response = $this->renderPage('ErrorPage', [
      'message' => 'Something went wrong. Please try disabling and re-enabling the Media Frames app.',
      'reportLink' => $reportLink,
    ]);
    $response->setStatus(500);
    return $response;
  }
}
