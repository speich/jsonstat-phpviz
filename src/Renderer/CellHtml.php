<?php

namespace jsonstatPhpViz\Renderer;

use DOMElement;
use DOMException;
use DOMNode;
use jsonstatPhpViz\DOM\ClassList;
use jsonstatPhpViz\FormatterCell;
use jsonstatPhpViz\Reader;
use jsonstatPhpViz\UtilArray;

use function count;

/**
 * Handle rendering of HTML table cells.
 * @see CellInterface
 */
class CellHtml extends AbstractCell
{
    protected TableHtml $table;

    /**
     * Construct the cell renderer.
     * @param FormatterCell $cellFormatter
     * @param Reader $reader
     * @param TableHtml $rendererTable
     */
    public function __construct(FormatterCell $cellFormatter, Reader $reader, TableHtml $rendererTable)
    {
        parent::__construct($cellFormatter, $reader);
        $this->table = $rendererTable;
    }

    /**
     * Add the first cell to a row of the table header.
     * Adds the first cell to a row of the table header. This can either be a label or a value cell,
     * since there are some tables that don't have header label cells or header value cells.
     * @param int $rowIdx row index
     * @return void
     * @throws DOMException
     */
    public function addFirstCellHeader(int $rowIdx): void
    {
        $this->table->dom->appendRow($this->table->head);
        if ($this->table->numRowDim > 0) {
            $this->addLabelCellHeader(0, $rowIdx);
        }
    }

    /**
     * Add the first cell to a row of the table body.
     * Adds the first cell to a row of the table body. This can either be a label or a value cell,
     * since there are some tables that don't have body label cells.
     * Note: The row index of the table body restarts at zero.
     * @param int $offset
     * @param int $rowIdx
     * @return void
     * @throws DOMException
     */
    public function addFirstCellBody(int $offset, int $rowIdx): void
    {
        $this->table->dom->appendRow($this->table->body);
        if ($this->table->numLabelCols > 0) {
            $this->addLabelCellBody(0, $rowIdx);
        }
        $this->addValueCellBody($offset, $rowIdx);
    }

    /**
     * Add a label cell to a row of the table header.
     * Adds a category label to the cell.
     * @param int $dimIdx dimension index
     * @param int $rowIdx row index
     * @return void
     * @throws DOMException
     */
    public function addLabelCellHeader(int $dimIdx, int $rowIdx): void
    {
        $label = null;
        $scope = null;
        $css = null;
        if ($this->table->isLastRowHeader($rowIdx)) {
            $id = $this->reader->getDimensionId($this->table->numOneDim + $dimIdx);
            $label = $this->reader->getDimensionLabel($id);
            $scope = 'col';
            $css = 'rowdim'.($dimIdx + 1);
        }
        $cell = $this->addCellHeader($label);
        $this->setAttrCellHeader($cell, $scope);
        if ($css) {
            $cell->setAttribute('class', $css);
        }
    }

    /**
     * Append a label cell to the row of the table body.
     * Note: The row index of the table body restarts at zero.
     * @param int $dimIdx dimension index
     * @param int $rowIdx row index
     * @return void
     * @throws DOMException
     */
    public function addLabelCellBody(int $dimIdx, int $rowIdx): void
    {
        $table = $this->table;
        $rowStrides = UtilArray::getStrides($table->rowDims);
        $stride = $rowStrides[$dimIdx];
        if ($table->useRowSpans === false || $rowIdx % $stride === 0) {
            $label = $rowIdx % $stride === 0 ? $this->getRowLabel($dimIdx, $rowIdx) : null;
            $rowspan = $table->useRowSpans && $stride > 1 ? $stride : null;
            $scope = $stride > 1 ? 'rowgroup' : 'row';
            $cell = $this->addCellHeader($label);
            $this->table->body->lastChild->appendChild($cell);  // moves already appended cell from thead to tbody
            $this->setAttrCellHeader($cell, $scope, null, $rowspan);
            $this->setCssLabelCell($cell, $dimIdx, $rowIdx, $stride);
        }
    }

    /**
     * Add a value cell to a row of the table header.
     * @param int $offset index of the JSON-stat value array
     * @param int $rowIdx row index
     * @return void
     * @throws DOMException
     */
    public function addValueCellHeader(int $offset, int $rowIdx): void
    {
        // remember: we render two rows with headings per column dimension,
        //  e.g., one for the dimension label and one for the category label
        $dimIdx = $this->table->numRowDim + (int)floor($rowIdx / 2);
        if ($this->table->isDimensionRowHeader($rowIdx)) {
            // set attributes for dimension label cell
            $dimId = $this->reader->getDimensionId($this->table->numOneDim + $dimIdx);
            $label = $this->reader->getDimensionLabel($dimId);
            $colspan = $this->calcColspanDimHeader($dimIdx);    // prev stride
        } else {
            // set attributes for category label cell
            $label = $this->getCategoryLabel($offset, $dimIdx);
            $colspan = $this->calcColspanCategoryHeader($dimIdx);   // stride
        }
        if ($colspan === null || $offset % $colspan === 0) {
            $scope = $colspan === null ? 'col' : 'colgroup';
            $cell = $this->addCellHeader($label);
            $this->setAttrCellHeader($cell, $scope, $colspan);
        }
    }


    /**
     * Append a value cell to the last row of the table body.
     * Note: The row index of the table body restarts at zero
     * @param int $offset index of the JSON-stat value array
     * @param int $rowIdx row index
     * @return void
     * @throws DOMException
     */
    public function addValueCellBody(int $offset, int $rowIdx): void
    {
        $doc = $this->table->doc;
        $val = $this->reader->data->value[$offset];
        $val = $this->formatter->formatValueCell($val, $offset);
        $cell = $doc->createElement('td');
        $cell->appendChild($doc->createTextNode($val));
        $this->table->body->lastChild->appendChild($cell);
    }

    /**
     * Add the last cell to a row of the table header.
     * @param int $offset index of the JSON-stat value array
     * @param int $rowIdx row index
     * @return void
     * @throws DOMException
     */
    public function addLastCellHeader(int $offset, int $rowIdx): void
    {
        if (count($this->table->colDims) === 0) {
            $this->addCellHeader();
        } else {
            $this->addValueCellHeader($offset, $rowIdx);
        }
    }

    /**
     * Add the last cell to a row of the table body.
     * Note: the row index of the table body restarts at zero
     * @param int $offset index of the JSON-stat value array
     * @param int $rowIdx row index
     * @return void
     * @throws DOMException
     */
    public function addLastCellBody(int $offset, int $rowIdx): void
    {
        $this->addValueCellBody($offset, $rowIdx);
    }

    /**
     * Sets the CSS class of the body row
     * @param DOMElement $cell
     * @param int $cellIdx
     * @param int $rowIdxBody
     * @param int $rowStride
     */
    protected function setCssLabelCell(DOMElement $cell, int $cellIdx, int $rowIdxBody, int $rowStride): void
    {
        $cl = new ClassList($cell);
        $product = $this->table->shape[$cellIdx] * $rowStride;
        $css = 'rowdim'.($cellIdx + 1);
        $modulo = $rowIdxBody % $product;
        if ($rowIdxBody % $rowStride === 0) {
            $cl->add($css);
        }
        if ($modulo === 0) {
            $cl->add($css, 'first');
        } elseif ($modulo === $product - $rowStride) {
            $cl->add($css, 'last');
        }
    }


    /**
     * Create, add and return a header cell element.
     * @param ?String $label cell content
     * @return DOMElement table cell element
     * @throws DOMException
     */
    protected function addCellHeader(?string $label = null): DOMNode
    {
        $doc = $this->table->doc;
        $cell = $doc->createElement('th');
        $label = $this->formatter->formatHeaderCell($label);
        $cell->appendChild($doc->createTextNode($label));

        return $this->table->head->lastChild->appendChild($cell);
    }

    /**
     * @param ?String $scope scope of cell
     * @param ?String $colspan number of columns to span
     * @param ?String $rowspan number of rows to span
     */
    protected function setAttrCellHeader(
        DOMElement $cell,
        ?string $scope = null,
        ?string $colspan = null,
        ?string $rowspan = null
    ): void {
        if ($scope !== null) {
            $cell->setAttribute('scope', $scope);
        }
        if ($colspan !== null) {
            $cell->setAttribute('colspan', $colspan);
        }
        if ($rowspan !== null) {
            $cell->setAttribute('rowspan', $rowspan);
        }
    }

    /**
     * Calculate the colspan for the dimension label cell of the table header.
     * @param int $dimIdx dimension index
     * @return int|null
     */
    protected function calcColspanDimHeader(int $dimIdx): ?int
    {
        $stride = $this->table->strides[$dimIdx];
        // note: $this->table->strides[$dimIdx - 1] would have entry missing for the first dim
        $prevStride = $stride * $this->table->shape[$dimIdx];

        return $prevStride > 1 ? $prevStride : null;
    }

    /**
     * Calculate the colspan for the category label cell of the table header.
     * @param int $dimIdx dimension index
     * @return int|null
     */
    protected function calcColspanCategoryHeader(int $dimIdx): ?int
    {
        $stride = $this->table->strides[$dimIdx];

        return $stride > 1 ? $stride : null;
    }
}