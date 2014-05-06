<?php

namespace Nette\Application\Responses;

use Nette;

/**
 * CSV download response.
 * Under New BSD license.
 *
 * @package Nette\Application\Responses
 */
class CsvResponse extends Nette\Object implements Nette\Application\IResponse
{

	const COMMA = ',',
		SEMICOLON = ';',
		TAB = '	';

	/** @var array */
	protected $data;

	/** @var string */
	protected $name;

	/** @var bool */
	protected $addHeading;

	/** @var string */
	protected $glue = self::COMMA;

	/** @var string */
	protected $outputCharset;

	/** @var string */
	protected $contentType = 'text/csv';

	/** @var callable */
	protected $headingFormatter, $dataFormatter;


	/**
	 * In accordance with Nette Framework accepts only UTF-8 input. For output @see setOutputCharset().
	 * @param array[]|\Traversable $data
	 * @param string $name
	 * @param bool $addHeading
	 * @throws \InvalidArgumentException
	 */
	public function __construct($data, $name = 'output.csv', $addHeading = TRUE)
	{
		if ($data instanceof \Traversable) {
			$data = iterator_to_array($data);
		}

		if (!is_array($data)) {
			$invalid = TRUE;
		}

		if (isset($invalid)) {
			throw new \InvalidArgumentException(__CLASS__.": data must be two dimensional array or instance of Traversable.");
		}

		$this->data = array_values($data);
		$this->name = $name;
		$this->addHeading = $addHeading;

		$this->setHeadingFormatter(__CLASS__.'::firstUpperNoUnderscoresFormatter');
	}


	/**
	 * @param string $glue
	 * @return self
	 * @throws \InvalidArgumentException
	 */
	public function setGlue($glue)
	{
		if (empty($glue) || preg_match('/[\n\r"]/s', $glue)) {
			throw new \InvalidArgumentException(__CLASS__.": glue cannot be an empty or reserved character.");
		}
		$this->glue = $glue;
		return $this;
	}


	/**
	 * @param string $charset
	 * @return self
	 */
	public function setOutputCharset($charset)
	{
		$this->outputCharset = $charset;
		return $this;
	}


	/**
	 * @param string $contentType
	 * @return self
	 */
	public function setContentType($contentType)
	{
		$this->contentType = $contentType;
		return $this;
	}


	/**
	 * @param callable $formatter
	 * @return self
	 * @throws \InvalidArgumentException
	 */
	public function setHeadingFormatter($formatter)
	{
		if (!is_callable($formatter)) {
			throw new \InvalidArgumentException(__CLASS__.": heading formatter must be callable.");
		}
		$this->headingFormatter = $formatter;
		return $this;
	}


	/**
	 * @param callable $formatter
	 * @return self
	 * @throws \InvalidArgumentException
	 */
	public function setDataFormatter($formatter)
	{
		if (!is_callable($formatter)) {
			throw new \InvalidArgumentException(__CLASS__.": data formatter must be callable.");
		}
		$this->dataFormatter = $formatter;
		return $this;
	}


	/**
	 * @param string $heading
	 * @return string
	 */
	public static function firstUpperNoUnderscoresFormatter($heading)
	{
		$heading = str_replace("_", ' ', $heading);
		$heading = mb_strtoupper(mb_substr($heading, 0, 1)) . mb_substr($heading, 1);
		return $heading;
	}


	/**
	 * Sends response to output.
	 * @param Nette\Http\IRequest $httpRequest
	 * @param Nette\Http\IResponse $httpResponse
	 */
	public function send(Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse)
	{
		$httpResponse->setContentType($this->contentType, $this->outputCharset);

		if (empty($this->name)) {
			$httpResponse->setHeader('Content-Disposition', 'attachment');
		} else {
			$httpResponse->setHeader('Content-Disposition', 'attachment; filename="' . $this->name . '"');
		}

		$data = $this->formatCsv();

		$httpResponse->setHeader('Content-Length', strlen($data));
		print $data;
	}


	protected function formatCsv()
	{
		if (empty($this->data)) {
			return '';
		}

		ob_start();
		$buffer = fopen("php://output", 'w');
		// if output charset is not UTF-8
		$recode = $this->outputCharset && strcasecmp($this->outputCharset, 'utf-8');

		foreach ($this->data as $n => $row) {
			if ($row instanceof \Traversable) {
				$row = iterator_to_array($row);
			}
			if (!is_array($row)) {
				throw new \InvalidArgumentException(__CLASS__.": row $n must be array or instance of Traversable, " . gettype($row) . ' given.');
			}

			if ($n === 0 && $this->addHeading) {
				$labels = array_keys($row);
				if ($this->headingFormatter || $recode) {
					foreach ($labels as &$label) {
						if ($this->headingFormatter) {
							$label = call_user_func($this->headingFormatter, $label);
						}
						if ($recode) {
							$label = iconv('utf-8', "$this->outputCharset//TRANSLIT", $label);
						}
					}
				}
				fputcsv($buffer, $labels, $this->glue);
			}

			if ($this->dataFormatter || $recode) {
				foreach ($row as &$value) {
					if ($this->dataFormatter) {
						$value = call_user_func($this->dataFormatter, $value);
					}
					if ($recode) {
						$value = iconv('utf-8', "$this->outputCharset//TRANSLIT", $value);
					}
				}
			}

			fputcsv($buffer, $row, $this->glue);
		}

		fclose($buffer);
		return ob_get_clean();
	}
}