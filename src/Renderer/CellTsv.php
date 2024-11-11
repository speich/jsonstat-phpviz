<?php

namespace jsonstatPhpViz\Renderer;

use jsonstatPhpViz\FormatterCell;
use jsonstatPhpViz\Reader;
use jsonstatPhpViz\UtilArray;

use function count;

/**
 * Handle rendering of TAB separated items (cells).
 * @see CellInterface
 */
class CellTsv extends AbstractCell
{
    protected TableTsv $table;

    /**
     * internal reference to the TAB separated string
     * @var string
     */
    private string $tsv;

    /**
     * @param FormatterCell $cellFormatter
     * @param Reader $reader
     * @param TableTsv $rendererTable
     */
    public function __construct(FormatterCell $cellFormatter, Reader $reader, TableTsv $rendererTable)
    {
        parent::__construct($cellFormatter, $reader);
        $this->table = $rendererTable;
        $this->tsv = &$rendererTable->getTsv();
    }

    /**
     * Add the first category label to the header line.
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
     * Add a category label to the table header.
     * @param int $dimIdx dimension index
     * @param int $rowIdx row index
     * @return void
     */
    public function addLabelCellHeader(int $dimIdx, int $rowIdx): void
    {
        $label = '';
        $table = $this->table;
        if ($table->repeatLabels || $table->isLastRowHeader($rowIdx)) {
            $id = $this->reader->getDimensionId($table->numOneDim + $dimIdx);
            $label = $this->reader->getDimensionLabel($id);
        }
        $this->tsv .= $this->formatter->formatHeaderCell($label).$table->separatorCol;
    }

    /**
     * Add the category label of the first dimension to the table body.
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
     * Append a category label to the table body.
     * @param int $dimIdx
     * @param int $rowIdx
     * @return void
     */
    public function addLabelCellBody(int $dimIdx, int $rowIdx): void
    {
        $table = $this->table;
        $rowStrides = UtilArray::getStrides($table->rowDims);
        $stride = $rowStrides[$dimIdx];
        $label = '';
        if ($table->repeatLabels || $rowIdx % $stride === 0) {
            $label = $this->getRowLabel($dimIdx, $rowIdx);
        }
        $this->tsv .= $this->formatter->formatHeaderCell($label).$table->separatorCol;
    }

    /**
     * Add the last cell to the header line.
     * @param int $offset value index
     * @param int $rowIdx row index
     * @return void
     */
    public function addLastCellHeader(int $offset, int $rowIdx): void
    {
        if (count($this->table->colDims) !== 0) {
            $this->addValueCellHeader($offset, $rowIdx);
        }
        $this->tsv .= $this->table->separatorRow;
    }

    /**
     * Add a value cell to the table header.
     * @param int $offset value index
     * @param int $rowIdx row index
     * @return void
     */
    public function addValueCellHeader(int $offset, int $rowIdx): void
    {
        // remember: we render two rows with headings per column dimension,
        //  e.g., one for the dimension label and one for the category label
        $table = $this->table;
        $dimIdx = $table->numRowDim + (int)floor($rowIdx / 2);
        $id = $this->reader->getDimensionId($table->numOneDim + $dimIdx);
        if ($table->isDimensionRowHeader($rowIdx)) {
            $label = $this->reader->getDimensionLabel($id);
        } else {
            $label = $this->getCategoryLabel($offset, $dimIdx);
        }
        $this->tsv .= $this->formatter->formatHeaderCell($label).$table->separatorCol;
    }

    /**
     * Add the last cell to a row of the table body.
     * @param int $offset value index
     * @param int $rowIdx row index
     * @return void
     */
    public function addLastCellBody(int $offset, int $rowIdx): void
    {
        $this->addValueCellBody($offset, $rowIdx);
        $this->tsv .= $this->table->separatorRow;
    }

    /**
     * Appends cells with values to the row.
     * Inserts a HTMLTableCellElement at the end of the row with a value taken from the values at given offset.
     * @param int $offset value index
     * @param int $rowIdx row index
     * @return void the content of the cell
     */
    public function addValueCellBody(int $offset, int $rowIdx): void
    {
        $val = $this->reader->data->value[$offset];
        $this->tsv .= $this->formatter->formatValueCell($val, $offset).$this->table->separatorCol;
    }
}