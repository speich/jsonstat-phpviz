<?php

namespace jsonstatPhpViz\Renderer;

use jsonstatPhpViz\FormatterCell;
use jsonstatPhpViz\Reader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TableExcel extends AbstractTable
{
    /**
     * an instance of the PhpSpreadsheet
     * @var Spreadsheet
     */
    private Spreadsheet $xls;

    /**
     * the current worksheet of the PhpSpreadsheet
     * @var Worksheet
     */
    private Worksheet $worksheet;

    /*
     * the writer used for rendering (saving), defaults to Xlsx.
     */
    private IWriter $writer;

    /**
     * if this instance is set, it's style method will be called
     * after building the table when rendering it.
     * @var StylerExcel|null
     */
    public ?StylerExcel $styler = null;

    /**
     * number of rows used for the caption
     * @var int
     */
    public int $numCaptionRows = 0;

    public function __construct(Reader $jsonStatReader, ?int $numRowDim = null)
    {
        parent::__construct($jsonStatReader, $numRowDim);
        $this->xls = new Spreadsheet();
        $this->worksheet = $this->xls->getActiveSheet();
        $this->writer = new Xlsx($this->xls);
    }

    /**
     * Set the writer to be used when rendering the output.
     * @param IWriter $writer
     *
     * @return void
     */
    public function setWriter(IWriter $writer): void
    {
        $this->writer = $writer;
    }

    /**
     * Return a new instance of the cell renderer.
     * @return CellInterface
     */
    protected function newCellRenderer(): CellInterface
    {
        $formatter = new FormatterCell($this->reader);
        return new CellExcel($formatter, $this->reader, $this);
    }

    /**
     * Render the table in memory.
     * Writes the file to memory and then returns it as a binary string.
     * @return string binary, zipped string
     * @throws Exception
     */
    public function render(): string
    {
        $this->build();

        $this->styler?->style($this);
        $this->getActiveWorksheet()->setSelectedCell('A1');    // there doesn't seem to be a deselect method

        $fp = fopen('php://memory', 'rwb');
        $this->writer->save($fp);
        rewind($fp);
        $content = '';
        while (!feof($fp)) {
            $content .= fread($fp, 8000);
        }
        fclose($fp);
        return $content;
    }

    /**
     * Create and insert the caption.
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function addCaption(): void
    {
        $this->worksheet->setCellValue([1, 1], $this->caption);
        $this->worksheet->mergeCells([1, 1, $this->numLabelCols + $this->numValueCols, 1]);
        ++$this->numCaptionRows;
    }

    /**
     * Set the caption automatically.
     * Sets the caption from the optional JSON-stat label property.
     * @return void
     */
    public function readCaption(): void
    {
        if (property_exists($this->reader->data, 'label')) {
            $this->caption = $this->reader->data->label;
        }
    }

    /**
     * Return the row index of the first body row.
     * This returns the row index adjusted by the caption and header rows.
     * @return int
     */
    public function getRowIdxBodyAdjusted(): int
    {
        $numRows = 0;
        if ($this->caption) {
            $numRows += $this->numCaptionRows;
        }
        $numRows += $this->numHeaderRows;
        if ($this->noLabelLastDim) {
            --$numRows;
        }

        return $numRows + 1;
    }

    /**
     * @return Spreadsheet
     */
    public function getSpreadSheet(): Spreadsheet
    {
        return $this->xls;
    }

    /**
     * Return the active worksheet.
     * @return Worksheet
     */
    public function getActiveWorksheet(): Worksheet
    {
        return $this->worksheet;
    }
}