<?php

namespace jsonstatPhpViz\Renderer;

use jsonstatPhpViz\Formatter;
use jsonstatPhpViz\FormatterCell;
use jsonstatPhpViz\Reader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
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
        $formatter = new FormatterCell($this->reader, $this->formatter);
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
        $this->styleCaption();
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
     * Create the table head and append header cells, row by row to it.
     */
    public function addHeaders(): void
    {
        parent::addHeaders();
        $this->styleHeaders();
    }


    public function addRows(): void
    {
        parent::addRows();
        $this->styleLabelCellBody();
        $this->styleValueCellBody();
        $this->worksheet->setSelectedCell('A1');    // there doesn't seem to be a deselect method
    }

    /**
     * Style the header cells of the current worksheet.
     * @return void
     */
    private function styleHeaders(): void
    {
        $fromCol = 1;
        $fromRow = $this->getRowIdxBodyAdjusted() - $this->numHeaderRows + 1;
        $toCol = $this->numLabelCols + $this->numValueCols;
        $toRow = $this->getRowIdxBodyAdjusted() - 1;
        $style = $this->worksheet->getStyle([$fromCol, $fromRow, $toCol, $toRow]);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $toRow += array_product($this->rowDims);
        $style = $this->worksheet->getStyle([$fromCol, $fromRow, $toCol, $toRow]);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        for ($colIdx = 1; $colIdx < $toCol + 1; $colIdx++) {
            $this->worksheet->getColumnDimensionByColumn($colIdx)->setAutoSize(true);
        }
    }

    /**
     * Style the caption cells of the current worksheet.
     * @return void
     */
    private function styleCaption(): void
    {
        $this->worksheet->getRowDimension(1)->setRowHeight(24);
        $style = $this->worksheet->getStyle([1, 1, 1, 1]);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    }

    /**
     * Style the label cells of the body.
     * Set the alignment of the value cells to left.
     */
    private function styleLabelCellBody(): void
    {
        $fromRow = $this->getRowIdxBodyAdjusted();
        $toRow = $fromRow + array_product($this->rowDims);
        $fromCol = ($this->numLabelCols === 0 ? 1 : $this->numLabelCols) + 1;
        $toCol = $fromCol + $this->numLabelCols;
        $style = $this->worksheet->getStyle([$fromCol, $fromRow, $toCol, $toRow]);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }


    /**
     * Style the value cells of the body.
     * Set the alignment of the value cells to right.
     */
    private function styleValueCellBody(): void
    {
        $fromRow = $this->getRowIdxBodyAdjusted();
        $toRow = $fromRow + array_product($this->rowDims);
        $fromCol = ($this->numLabelCols === 0 ? 1 : $this->numLabelCols) + 1;
        $toCol = $this->numLabelCols + $this->numValueCols;
        $style = $this->worksheet->getStyle([$fromCol, $fromRow, $toCol, $toRow]);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    /**
     * Return the row index of the first body row.
     * This returns the row index adjusted by the caption and header rows. The caption is assumed to be just one row.
     * @return int
     */
    public function getRowIdxBodyAdjusted(): int
    {
        $numRows = 0;
        if ($this->caption) {
            ++$numRows;
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