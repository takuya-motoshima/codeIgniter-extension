<?php
/**
 * HTTP Response util class
 *
 * @author     Takuya Motoshima <https://www.facebook.com/takuya.motoshima.7>
 * @license    MIT License
 * @copyright  2017 Takuya Motoshima
 */
namespace X\Util;

use X\Constant\HttpStatus;
use X\Util\Loader;
final class HttpResponse
{

  /**
   * @var array $data
   */
  private $data = [];

  /**
   * @var int $statusCode
   */
  private $statusCode;

  /**
   * @var array $jsonOption
   */
  private $jsonOption = [
    JSON_FORCE_OBJECT => false,
    JSON_PRETTY_PRINT => false,
    JSON_UNESCAPED_SLASHES => true,
    JSON_UNESCAPED_UNICODE => false,
  ];

  /**
   * 
   * Response JSON
   *
   * @throws LogicException
   * @return void
   */
  public function json()
  {
    $option = 0;
    foreach($this->jsonOption as $key => $enabled) {
      if ($enabled) {
        $option = $option | $key;
      }
    }

    // Reset options
    $this->jsonOption = [
      JSON_FORCE_OBJECT => false,
      JSON_PRETTY_PRINT => false,
      JSON_UNESCAPED_SLASHES => true,
      JSON_UNESCAPED_UNICODE => false,
    ];

    // Generate json
    $json = json_encode($this->data, $option);
    if ($json === false) {
      throw new \LogicException(sprintf('Failed to parse json string \'%s\', error: \'%s\'', $this->data, json_last_error_msg()));
    }
    ob_clean();
    $ci =& \get_instance();
    $this->setCorsHeader($ci);
    $ci->output
      ->set_status_header($this->statusCode ?? \X\Constant\HTTP_OK)
      ->set_content_type('application/json', 'UTF-8')
      ->set_output($json);
  }

  /**
   * 
   * Set response json option
   *
   * @param int $option JSON_FORCE_OBJECT | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
   * @param bool $enabled
   * @return void
   */
  public function jsonOption(int $option, bool $enabled)
  {
    $this->jsonOption[$option] = $enabled;
    return $this;
  }

  /**
   * 
   * Response HTML
   *
   * @param  string  $htmlCode
   * @param  string $char
   * @return void
   */
  public function html(string $htmlCode, string $char = 'UTF-8')
  {
    $ci =& \get_instance();
    $this->setCorsHeader($ci);
    $ci->output
      ->set_content_type('text/html', $char)
      ->set_output($htmlCode);
  }

  /**
   * 
   * Response HTML for template
   *
   * @param  string $templatePath
   * @param  string $char
   * @return void
   */
  public function template(string $templatePath, string $char = 'UTF-8')
  {
    static $template;
    $template = $template ?? new \X\Util\Template();
    self::html($template->load($templatePath, $this->data));
  }

  /**
   * 
   * Response javascript
   *
   * @param  string $scriptCode
   * @param  string $char
   * @return void
   */
  public function javascript(string $scriptCode, string $char = 'UTF-8')
  {
    ob_clean();
    $ci =& \get_instance();
    $this->setCorsHeader($ci);
    $ci->output
      ->set_content_type('application/javascript', $char)
      ->set_output($scriptCode);
  }

  /**
   * 
   * Response text
   *
   * @param  string $text
   * @param  string $char
   * @return void
   */
  public function text(string $text, string $char = 'UTF-8')
  {
    ob_clean();
    $ci =& \get_instance();
    $this->setCorsHeader($ci);
    $ci->output
      ->set_content_type('text/plain', $char)
      ->set_output($text);
  }

  /**
   * 
   * Response download
   *
   * @param  string $filename
   * @param  string $data
   * @param  bool $setMime
   * @return void
   */
  public function download(string $filename, string $data = '', bool $setMime = FALSE)
  {
    $ci =& \get_instance();
    $ci->load->helper('download');
    ob_clean();
    force_download($filename, $data, $setMime);
  }

  /**
   * 
   * Response image
   *
   * @param  string $imagePath
   * @return void
   */
  public function image(string $imagePath)
  {
    $ci =& \get_instance();
    $ci->load->helper('file');
    ob_clean();
    $ci->output
      ->set_content_type(get_mime_by_extension($imagePath))
      ->set_output(file_get_contents($imagePath));
  }

  /**
   * 
   * Response error
   *
   * @param  string $message
   * @param  int $statusCode
   * @return void
   */
  public function error(string $message, int $statusCode = \X\Constant\HTTP_INTERNAL_SERVER_ERROR)
  {
    $ci =& \get_instance();
    if ($ci->input->is_ajax_request()) {
      ob_clean();
      $this->setCorsHeader($ci);
      $ci->output
        ->set_header('Cache-Control: no-cache, must-revalidate')
        ->set_status_header($statusCode, rawurlencode($message))
        ->set_content_type('application/json', 'UTF-8');
    } else {
      show_error($message, $statusCode);
    }
  }

  /**
   * 
   * Set http status
   *
   * @param  int $statusCode
   * @return object
   */
  public function status(int $statusCode)
  {
    $this->statusCode = $statusCode;
    return $this;
  }

  /**
   * 
   * Set response data
   *
   * @param  mixed $key
   * @param  mixed $value
   * @return object
   */
  public function set($key, $value = null)
  {
    if (func_num_args() === 2) {
      if (!is_array($this->data)) {
        $this->data = [];
      }
      $this->data[$key] = $value;
    } else if (func_num_args() === 1) {
      $this->data = $key;
    }
    return $this;
  }

  /**
   * 
   * Clear response data
   *
   * @return object
   */
  public function clear()
  {
    $this->data[] = [];
    return $this;
  }

  /**
   * Set CORS header
   *
   * @param
   */
  public function setCorsHeader(\CI_Controller &$ci)
  {
    $allowOrigin = '*';
    if (!empty($_SERVER['HTTP_ORIGIN'])) {
      $allowOrigin = $_SERVER['HTTP_ORIGIN'];
    } else if (!empty($_SERVER['HTTP_REFERER'])) {
      $allowOrigin = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_SCHEME) . '://' . parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    }
    // $allowOrigin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    $ci->output
      ->set_header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization')
      ->set_header('Access-Control-Allow-Methods: GET, POST, OPTIONS')
      ->set_header('Access-Control-Allow-Credentials: true')
      ->set_header('Access-Control-Allow-Origin: ' . $allowOrigin);
  }
}