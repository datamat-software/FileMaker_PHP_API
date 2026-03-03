<?php
/**
 * @copyright Copyright (c) 2016 by 1-more-thing[](http://1-more-thing.com) All rights reserved.
 * @license BSD
 */
namespace airmoi\FileMaker\Parser;

use airmoi\FileMaker\FileMaker;
use airmoi\FileMaker\FileMakerException;
use airmoi\FileMaker\Object\Layout;

/**
 * Class used to parse FMPXMLLAYOUT structure
 *
 * @package FileMaker
 */
class FMPXMLLAYOUT
{
    private FileMaker $fm;

    private array $fields = [];
    private ?array $valueLists = null;
    private ?array $valueListTwoFields = null;
    private $xmlParser = null;
    private bool $isParsed = false;
    private ?string $fieldName = null;
    private ?string $valueList = null;
    private ?string $displayValue = null;
    private bool $insideData = false;

    private ?int $errorCode = null;   // wird im Original nirgends gesetzt – evtl. später sinnvoll

    public function __construct(FileMaker $fm)
    {
        $this->fm = $fm;
    }

    /**
     * @param string $xmlResponse
     * @return bool|FileMakerException
     * @throws FileMakerException
     */
    public function parse(string $xmlResponse)
    {
        if ($xmlResponse === '') {
            return $this->fm->returnOrThrowException('Did not receive an XML document from the server.');
        }

        $this->xmlParser = xml_parser_create();
        
        // Wichtig: xml_set_object() ist weg – direkt Callables übergeben
        xml_parser_set_option($this->xmlParser, XML_OPTION_CASE_FOLDING, false);
        xml_parser_set_option($this->xmlParser, XML_OPTION_TARGET_ENCODING, 'UTF-8');

        xml_set_element_handler(
            $this->xmlParser,
            [$this, 'start'],
            [$this, 'end']
        );

        xml_set_character_data_handler(
            $this->xmlParser,
            [$this, 'cdata']
        );

        if (!xml_parse($this->xmlParser, $xmlResponse, true)) {
            $error = sprintf(
                'XML error: %s at line %d',
                xml_error_string(xml_get_error_code($this->xmlParser)),
                xml_get_current_line_number($this->xmlParser)
            );
            return $this->fm->returnOrThrowException($error);
        }

        $this->xmlParser = null;

        if ($this->errorCode !== null) {
            return $this->fm->returnOrThrowException(null, $this->errorCode);
        }

        $this->isParsed = true;
        return true;
    }

    /**
     * @param Layout $layout
     * @return bool|FileMakerException
     * @throws FileMakerException
     */
    public function setExtendedInfo(Layout $layout)
    {
        if (!$this->isParsed) {
            return $this->fm->returnOrThrowException('Attempt to set extended information before parsing data.');
        }

        $layout->valueLists = $this->valueLists ?? [];
        $layout->valueListTwoFields = $this->valueListTwoFields ?? [];

        foreach ($this->fields as $fieldName => $fieldInfos) {
            try {
                $field = $layout->getField($fieldName);
                if (!FileMaker::isError($field)) {
                    $field->styleType = $fieldInfos['styleType'] ?? null;
                    $field->valueList  = !empty($fieldInfos['valueList']) ? $fieldInfos['valueList'] : null;
                }
            } catch (\Exception $e) {
                // Field may be missing (e.g. in portal) → silent
            }
        }

        return true;
    }

    private function start($parser, string $type, array $datas): void
    {
        $datas = $this->fm->toOutputCharset($datas);

        switch ($type) {
            case 'FIELD':
                $this->fieldName = $datas['NAME'] ?? null;
                break;

            case 'STYLE':
                if ($this->fieldName !== null) {
                    $this->fields[$this->fieldName] = [
                        'styleType'  => $datas['TYPE'] ?? null,
                        'valueList'  => $datas['VALUELIST'] ?? null,
                    ];
                }
                break;

            case 'VALUELIST':
                $name = $datas['NAME'] ?? null;
                if ($name !== null) {
                    $this->valueLists[$name] = [];
                    $this->valueListTwoFields[$name] = [];
                    $this->valueList = $name;
                }
                break;

            case 'VALUE':
                $this->displayValue = $datas['DISPLAY'] ?? null;
                if ($this->valueList !== null && $this->displayValue !== null) {
                    $this->valueLists[$this->valueList][] = '';
                }
                break;
        }

        $this->insideData = false;
    }

    private function end($parser, string $type): void
    {
        switch ($type) {
            case 'FIELD':
                $this->fieldName = null;
                break;

            case 'VALUELIST':
                $this->valueList = null;
                break;
        }

        $this->insideData = false;
    }

    public function cdata($parser, string $data): void
    {
        if ($this->valueList === null || !preg_match('|\S|', $data)) {
            return;
        }

        $data = $this->fm->toOutputCharset($data);

        if ($this->insideData && $this->displayValue !== null) {
            // Anhängen an vorherigen Wert (Multi-Line)
            $prev = &$this->valueListTwoFields[$this->valueList][$this->displayValue];
            $prev .= $data;
        } else {
            $this->valueListTwoFields[$this->valueList][$this->displayValue] = $data;
        }

        $idx = count($this->valueLists[$this->valueList]) - 1;
        $this->valueLists[$this->valueList][$idx] .= $data;

        $this->insideData = true;
    }

    /**
     * @param array $array
     * @param array $values
     * @return array|bool
     * @deprecated scheint im aktuellen Code gar nicht mehr benutzt zu werden
     */
    public function associativeArrayPush(array &$array, array $values)
    {
        foreach ($values as $key => $value) {
            $array[$key] = $value;
        }
        return $array;
    }
}