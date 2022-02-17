<?php

namespace jsonstatPhpViz\src;

use DOMElement;
use DOMException;
use DOMNode;
use jsonstatPhpViz\src\DOM\ClassList;
use jsonstatPhpViz\src\DOM\Table;
use function array_slice;
use function count;

/**
 * Renders json-stat data as a html table.
 *
 * A table consists of a number of dimensions that are used to define the rows of the two-dimensional table
 * (referred to as row dimensions) and a number of dimensions that are used to define the columns of the table
 * (referred to as col dimensions). Each row dimension creates its own pre column, containing only category labels,
 * whereas the column dimensions contain the actual values.
 *
 * Setting the property numRowDim (number of row dimensions) defines how many of the dimensions are use for the rows,
 * beginning at the start of the ordered size array of the json-stat schema. Remaining dimensions are used for columns.
 * Dimensions of length one are excluded.
 *
 * Setting the property noLabelLastDim will skip the row in the table heading containing the labels of the last
 * dimension.
 *
 * Note 1: When rendering a table with rowspans (setting the useRowSpans property to true),
 * applying css might become complicated because of the irregular number of cells per row.
 *
 * Note 2: This code was directly translated from JavaScript jsonstat-viz
 * @see https://github.com/speich/jsonstat-viz
 *
 * @see www.json-stat.org
 */
class RendererTable
{
    /** @var int dimension of type row */
    public const DIM_TYPE_ROW = 1;

    /** @var int dimensions of type col */
    public const DIM_TYPE_COL = 2;

    /** @var Reader */
    protected Reader $reader;

    /* @var array $colDims dimensions used for columns containing values */
    protected array $colDims;

    /* @var array $rowDims dimensions used for rows containing labels, that make up the rows */
    protected array $rowDims;

    /** @var int number of dimensions of size one */
    protected int $numOneDim;

    /** @var int number of columns with values */
    protected int $numValueCols;

    /** @var int number of columns with labels */
    protected int $numLabelCols;

    /** @var int|null number of dimensions to be used for rows */
    protected ?int $numRowDim;

    /** @var DOMNode|Table */
    protected Table|DOMNode $table;

    /** @var int|float number of row headers */
    protected int|float $numHeaderRows;

    /** @var bool render the row with labels of last dimension? default = true */
    public bool $noLabelLastDim = false;

    /**
     * Render the table with rowspans ?
     * default = true
     * Note: When this is set to false, empty rowheaders might be created, which are an accessibility problem.
     * @var bool $useRowSpans
     */
    public bool $useRowSpans = true;

    /**
     * Exclude dimensions of size one from rendering.
     * Only excludes dimensions of size one, when each dimension with a lower index is also of size one.
     * @var bool
     */
    public ?bool $excludeOneDim = false;

    /** @var null|string|DOMNode caption of the table */
    public null|string|DOMNode $caption;

    /**
     *
     * @param Reader $jsonStatReader
     * @param int|null $numRowDim
     */
    public function __construct(Reader $jsonStatReader, ?int $numRowDim = null)
    {
        $this->reader = $jsonStatReader;
        $this->table = new Table();
        $this->numRowDim = $numRowDim;
        if (property_exists($this->reader->data, 'label')) {
            $this->caption = $this->escapeHtml($this->reader->data->label);
        }
    }

    /**
     * Set the number of dimensions to be used for rows.
     * @param int $numRowDim
     */
    public function setNumRowDim(int $numRowDim): void
    {
        $this->numRowDim = $numRowDim;
    }

    /**
     * Precalculate and cache often used numbers before rendering.
     * @return void
     */
    protected function init(): void
    {
        $dims = $this->reader->getDimensionSizes($this->excludeOneDim);
        $this->numRowDim = $this->numRowDim ?? $this->numRowDimAuto();
        $this->rowDims = $this->getDims($dims, self::DIM_TYPE_ROW);
        $this->colDims = $this->getDims($dims, self::DIM_TYPE_COL);
        $css = new ClassList($this->table->get());
        $css->add('jst-viz', 'numRowDims'.count($this->rowDims), 'lastDimSize'.$dims[count($dims) - 1]);
        // cache some often used numbers before rendering table
        $dimsAll = $this->reader->getDimensionSizes(false);
        $this->numOneDim = count($dimsAll) - count($this->rowDims) - count($this->colDims);
        $this->numValueCols = count($this->colDims) > 0 ? UtilArray::product($this->colDims) : 1;
        $this->numLabelCols = count($this->rowDims);
        $this->numHeaderRows = count($this->colDims) > 0 ? count($this->colDims) * 2 : 1; // add an additional row to label each dimension
    }

    /**
     * Returns the dimensions that can be used for rows or cols.
     * Constant dimensions (e.g. of length 1) are excluded.
     * @param array $dims
     * @param int $type 'row' or 'col' possible values are RendererTable::DIM_TYPE_ROW or RendererTable::DIM_TYPE_COL
     * @return array
     */
    public function getDims(array $dims, int $type = RendererTable::DIM_TYPE_ROW): array
    {

        return $type === 1 ? array_slice($dims, 0, $this->numRowDim) : array_slice($dims, $this->numRowDim);
    }

    /**
     * Renders the data as a html table.
     * Reads the value array and renders it as a table.
     * @param bool $asHtml render as html or DOMElement?
     * @return DOMElement|string table
     * @throws DOMException
     */
    public function render(bool $asHtml = true): string|DOMElement
    {
        $this->init();
        $this->caption();
        $this->rowHeaders();
        $this->rows();

        return $asHtml ? $this->table->toHtml() : $this->table->get();
    }

    /**
     * Creates the table head and appends header cells, row by row to it.
     * @throws DOMException
     */
    public function rowHeaders(): void
    {
        $tHead = $this->table->createTHead();
        for ($rowIdx = 0; $rowIdx < $this->numHeaderRows; $rowIdx++) {
            if ($this->noLabelLastDim === false || $rowIdx !== $this->numHeaderRows - 2) {
                $row = $this->table->appendRow($tHead);
                $this->headerLabelCells($row, $rowIdx);
                $this->headerValueCells($row, $rowIdx);
            }
        }
    }

    /**
     * Creates the table body and appends table cells row by row to it.
     * @throws DOMException
     */
    public function rows(): void
    {
        $rowIdx = 0;
        $tBody = $this->table->createTBody();
        for ($offset = 0, $len = $this->reader->getNumValues(); $offset < $len; $offset++) {
            if ($offset % $this->numValueCols === 0) {
                $row = $this->table->appendRow($tBody);
                $this->labelCells($row, $rowIdx);
                $rowIdx++;
            }
            $this->valueCell($row, $offset);
        }
    }

    /**
     * Creates the cells for the headers of the label columns
     * @param DOMElement $row
     * @param int $rowIdx
     * @throws DOMException
     */
    public function headerLabelCells(DOMNode $row, int $rowIdx): void
    {
        for ($k = 0; $k < $this->numLabelCols; $k++) {
            $label = null;
            $scope = null;

            if ($rowIdx === $this->numHeaderRows - 1) { // last header row
                $id = $this->reader->getDimensionId($this->numOneDim + $k);
                $label = $this->reader->getDimensionLabel($id);
                $scope = 'col';
            }
            $this->headerCell($row, $label, $scope);
        }
    }

    /**
     * Creates the cells for the headers of the value columns.
     * @param DOMNode $row
     * @param int $rowIdx
     * @throws DOMException
     */
    public function headerValueCells(DOMNode $row, int $rowIdx): void
    {
        if (count($this->colDims) === 0) {
            $this->headerCell($row);

            return;
        }

        $idx = floor($rowIdx / 2); // 0,1,2,3,... -> 0,0,1,1,2,2,...
        $dimIdx = $this->numOneDim + $this->numRowDim + $idx;
        $f = UtilArray::productUpperNext($this->colDims, $idx);
        for ($i = 0; $i < $this->numValueCols; $i++) {
            $colspan = null;
            $scope = 'col';
            $z = $rowIdx % 2;
            $id = $this->reader->getDimensionId($dimIdx);
            if ($z === 0) {
                $label = $this->reader->getDimensionLabel($id);
            } else {
                $catIdx = floor(($i % $f[0]) / $f[1]);
                $catId = $this->reader->getCategoryId($id, $catIdx);
                $label = $this->reader->getCategoryLabel($id, $catId);
            }
            if ($f[$z] > 1) {
                $colspan = $f[$z];
                $i += $colspan - 1; // colspan - 1 -> i++ $follows
                $scope = 'colgroup';
            }
            $cell = $this->headerCell($row, $label, $scope, $colspan);
            $row->appendChild($cell);
        }
    }

    /**
     * Appends cells with labels to the row.
     * Inserts the label as a HTMLTableHeaderElement at the end of the row.
     * @param DOMElement $row HTMLTableRow
     * @param int $rowIdxBody row index
     * @throws DOMException
     */
    protected function labelCells(DOMElement $row, int $rowIdxBody): void
    {
        for ($i = 0; $i < $this->numLabelCols; $i++) {
            $f = UtilArray::productUpperNext($this->rowDims, $i);
            $label = null;
            if ($rowIdxBody % $f[1] === 0) {
                $catIdx = floor($rowIdxBody % $f[0] / $f[1]);
                $id = $this->reader->getDimensionId($this->numOneDim + $i);
                $labelId = $this->reader->getCategoryId($id, $catIdx);
                $label = $this->reader->getCategoryLabel($id, $labelId);
            }
            $rowspan = null;
            $scope = 'row';
            if ($this->useRowSpans && $f[1] > 1) {
                $rowspan = $f[1];
                $scope = 'rowgroup';
            }
            if ($rowIdxBody % $f[1] === 0 || !$this->useRowSpans) {
                $cell = $this->headerCell($row, $label, $scope, null, $rowspan);
                $this->labelCellCss($cell, $i, $rowIdxBody);
                $row->appendChild($cell);
            }
        }
    }

    /**
     * Sets the css class of the body row
     * @param {HTMLTableCellElement} $cell
     * @param {String} $cellIdx
     * @param {String} $rowIdxBody
     */
    protected function labelCellCss($cell, $cellIdx, $rowIdxBody): void
    {
        $cl = new ClassList($cell);
        $f = UtilArray::productUpperNext($this->rowDims, $cellIdx);
        $css = 'rowdim'.($cellIdx + 1);
        $modulo = $rowIdxBody % $f[0];
        if ($rowIdxBody % $f[1] === 0) {
            $cl->add($css);
        }
        if ($modulo === 0) {
            $cl->add($css, 'first');
        } elseif ($modulo === $f[0] - $f[1]) {
            $cl->add($css, 'last');
        }
    }

    /**
     * Appends cells with values to the row.
     * Inserts a HTMLTableCellElement at the end of the row with a value taken from the values at given offset.
     * @param DOMNode $row
     * @param int $offset
     * @throws DOMException
     */
    public function valueCell(DOMNode $row, int $offset): void
    {
        $stat = $this->reader;
        $cell = $this->table->doc->createElement('td');
        $cell = $row->appendChild($cell);
        $cell->textContent = $stat->data->value[$offset]; // no need to escape
    }

    /**
     * Create and returns a header cell element.
     * @param DOMNode $row
     * @param {String} [str] cell content
     * @param {String} [scope] scope of cell
     * @param [colspan] number of columns to span
     * @param [rowspan] number of rows to span
     * @return DOMNode
     * @throws DOMException
     */
    public function headerCell(DOMNode $row, $str = null, $scope = null, $colspan = null, $rowspan = null): DOMNode
    {
        $cell = $this->table->doc->createElement('th');
        if ($scope !== null) {
            $cell->setAttribute('scope', $scope);
        }
        if ($str === null) {
            // otherwise, <th/> is created, which is invalid on a non-void element
            $cell->appendChild($this->table->doc->createTextNode(''));
        } else {
            $cell->textContent = $str;  // no need to escape
        }
        if ($colspan !== null) {
            $cell->setAttribute('colspan', $colspan);
        }
        if ($rowspan !== null) {
            $cell->setAttribute('rowspan', $rowspan);
        }

        return $row->appendChild($cell);
    }

    /**
     * Creates and inserts a caption.
     * @return DOMNode|string|null
     */
    public function caption(): DOMNode|string|null
    {
        if ($this->caption) {
            $caption = $this->table->insertCaption();
            $fragment = $this->table->doc->createDocumentFragment();
            $fragment->appendXML($this->caption);
            $caption->appendChild($fragment);
            $this->caption = $caption;
        }

        return $this->caption;
    }

    /**
     * Returns the default number of dimensions used for rows.
     * Uses at least two dimensions for the columns when there are more than 2 dimensions.
     * @return int
     */
    public function numRowDimAuto(): int
    {
        $dims = $this->reader->getDimensionSizes($this->excludeOneDim);

        return count($dims) === 2 ? 1 : count(array_slice($dims, 0, count($dims) - 2));
    }

    /**
     * Escape a string, so it can be safely inserted into html.
     * @param {String} text
     * @return string
     */
    public function escapeHtml($text): string
    {

        return htmlspecialchars($text, ENT_HTML5, 'UTF-8');
    }
}
