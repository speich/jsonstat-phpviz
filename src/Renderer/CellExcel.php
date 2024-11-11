<?php

namespace jsonstatPhpViz\Renderer;

use jsonstatPhpViz\FormatterCell;
use jsonstatPhpViz\Reader;
use jsonstatPhpViz\UtilArray;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

use function count;

/**
 * Handle rendering of html table cells.
 * @see CellInterface
 */
class CellExcel extends AbstractCell
{
    protected TableExcel $table;
    protected Worksheet $worksheet;

    /**
     * Instantiate the class with the cell formatter and the JSON-stat reader.
     * @param FormatterCell $cellFormatter
     * @param Reader $reader
     * @param TableExcel $rendererTable
     */
    public function __construct(FormatterCell $cellFormatter, Reader $reader, TableExcel $rendererTable)
    {
        parent::__construct($cellFormatter, $reader);
        $this->table = $rendererTable;
        $this->worksheet = $this->table->getActiveWorksheet();
    }

    /**
     * Add the first cell to a row of the table header.
     * @param int $rowIdx row index
     * @return void
     */
    public function addFirstCellHeader(int $rowIdx): void
    {
        if ($this->table->numRowDim > 0) {
            $this->addLabelCellHeader(0, $rowIdx);
        }
    }

    /**
     * Add the first cell to a row of the table body.
     * @param int $offset
     * @param int $rowIdx
     * @return void
     */
    public function addFirstCellBody(int $offset, int $rowIdx): void
    {
        for ($colIdx = 0; $colIdx < $this->table->numLabelCols; $colIdx++) {
            $this->addLabelCellBody($colIdx, $rowIdx);
        }
        $this->addValueCellBody($offset, $rowIdx);
    }

    /**
     * Add a label cell to a row of the table header.
     * @param int $dimIdx dimension index
     * @param int $rowIdx row index
     * @return void
     */
    public function addLabelCellHeader(int $dimIdx, int $rowIdx): void
    {
        $label = null;
        if ($this->table->isLastRowHeader($rowIdx)) {
            $id = $this->reader->getDimensionId($this->table->numOneDim + $dimIdx);
            $label = $this->reader->getDimensionLabel($id);
        }
        $this->addCellHeader($dimIdx + 1, $this->adjustYHeader($rowIdx), $label);
    }

    /**
     * Append a label cell to the row of the table body.
     * @param int $dimIdx dimension index
     * @param int $rowIdx row index
     * @return void
     * @throws Exception
     */
    public function addLabelCellBody(int $dimIdx, int $rowIdx): void
    {
        $table = $this->table;
        $rowStrides = UtilArray::getStrides($table->rowDims);
        $stride = $rowStrides[$dimIdx];
        if ($table->useRowSpans === false || $rowIdx % $stride === 0) {
            $label = $rowIdx % $stride === 0 ? $this->getRowLabel($dimIdx, $rowIdx) : null;
            $rowspan = $table->useRowSpans && $stride > 1 ? $stride : 0;
            $x = $dimIdx + 1;
            $y = $this->adjustYBody($rowIdx);
            $this->addCellHeader($x, $y, $label);
            if ($rowspan > 0) {
                $this->worksheet->mergeCells([$x, $y, $x, $y + $rowspan - 1]);
            }
        }
    }

    /**
     * Creates the cells for the headers of the value columns.
     * @param int $offset value index
     * @param int $rowIdx row index
     * @throws Exception
     */
    public function addValueCellHeader(int $offset, int $rowIdx): void
    {
        // remember: we render two rows with headings per column dimension,
        //  e.g., one for the dimension label and one for the category label
        $dimIdx = $this->table->numRowDim + (int)floor($rowIdx / 2);
        $stride = $this->table->strides[$dimIdx];
        $product = $this->table->shape[$dimIdx] * $stride;
        $id = $this->reader->getDimensionId($this->table->numOneDim + $dimIdx);
        if ($this->table->isDimensionRowHeader($rowIdx)) {
            // set attributes for dimension label cell
            $label = $this->reader->getDimensionLabel($id);
            $colspan = $product > 1 ? $product : 0;
        } else {
            // set attributes for category label cell
            $label = $this->getCategoryLabel($offset, $dimIdx);
            $colspan = $stride > 1 ? $stride : 0;
        }
        if ($colspan === 0 || $offset % $colspan === 0) {
            $x = $this->adjustX($offset);
            $y = $this->adjustYHeader($rowIdx);
            $this->addCellHeader($x, $y, $label);
            if ($colspan > 1) {
                $this->worksheet->mergeCells([$x, $y, $x + $colspan - 1, $y]);
            }
        }
    }

    /**
     * Append a value cell to the row.
     * Inserts a HTMLTableCellElement at the end of the row
     * with a value taken from the JSON-stat values attribute at the given offset.
     * @param int $offset
     * @param int $rowIdx
     * @return void the created table cell
     */
    public function addValueCellBody(int $offset, int $rowIdx): void
    {
        $x = $this->adjustX($offset);
        $y = $this->adjustYBody($rowIdx);
        $val = $this->reader->data->value[$offset];
        $val = $this->formatter->formatValueCell($val, $offset);
        $this->worksheet->setCellValue([$x, $y], $val);
    }

    /**
     * Add the last cell to a body row.
     * @param int $offset value index
     * @param int $rowIdx row index
     * @return void
     * @throws Exception
     */
    public function addLastCellHeader(int $offset, int $rowIdx): void
    {
        if (count($this->table->colDims) !== 0) {
            $this->addValueCellHeader($offset, $rowIdx);
        }
    }

    /**
     * Add the last cell to a body row.
     * @param int $offset value index
     * @param int $rowIdx row index
     * @return void
     */
    public function addLastCellBody(int $offset, int $rowIdx): void
    {
        $this->addValueCellBody($offset, $rowIdx);
    }

    /**
     * Set the content of the header cell.
     * @param $x $column
     * @param $y $row
     * @param ?String $label cell content
     * @return void table cell element
     */
    private function addCellHeader($x, $y, ?string $label = null): void
    {
        $label = $this->formatter->formatHeaderCell($label);
        $this->worksheet->setCellValueExplicit([$x, $y], $label, DataType::TYPE_STRING);
    }

    /**
     * Return the adjusted column index.
     * @param int $offset value index
     * @return int
     */
    protected function adjustX(int $offset): int
    {
        return $this->table->numLabelCols + ($offset % $this->table->numValueCols) + 1;
    }

    /**
     * Return the adjusted row index.
     * Add the caption to the row index.
     * @param int $rowIdx row index
     * @return int
     */
    protected function adjustYHeader(int $rowIdx): int
    {
        $y = $rowIdx + 1;
        if ($this->table->caption) {
            $y += $this->table->numCaptionRows;
        }

        return $y;
    }

    /**
     * Return the adjusted row index.
     * Add the caption and the header rows to the row index.
     * @param int $rowIdx row index
     * @return int
     */
    protected function adjustYBody(int $rowIdx): int
    {
        $y = $this->adjustYHeader($rowIdx);
        $y += $this->table->numHeaderRows;

        return $y;
    }

    /**
     * Escape the = character, which would start a formula
     * @param string $value
     * @return string
     */
    protected function escape(string $value): string {

    }
}