<?php

namespace jsonstatPhpViz\Renderer;

/**
 * Handles the rendering of table cells.
 *
 * There are four types of cells to render:
 *
 * |-----------------------------------------------------------|
 * | header label cell | header value cell | header value cell |
 * |===================|===================|===================|
 * |  body label cell  |  body value cell  |  body value cell  |
 * |-------------------|-------------------|-------------------|
 *
 * e.g.:
 *
 * |-----------------------------------------------------------|
 * |    OECD country   |     year 2003     |     year 2004     |
 * |===================|===================|===================|
 * |       Sweden      |    6.56574156     |    7.373480411    |
 * |-------------------|-------------------|-------------------|
 * |     Switzerland   |    4.033356027    |     4.31699694    |
 * |-------------------|-------------------|-------------------|
 * |         ...       |         ...       |         ...       |
 * |-------------------|-------------------|-------------------|
 */
interface CellInterface
{
    /**
     * Add the first cell to a row of the table header.
     * Adds the first cell to a row of the table header. This can either be a label or a value cell,
     * since there are some tables that don't have header label cells or header value cells.
     * @param int $rowIdx row index
     * @return void
     */
    public function addFirstCellHeader(int $rowIdx): void;

    /**
     * Add the first cell to a row of the table body.
     * Adds the first cell to a row of the table body. This can either be a label or a value cell,
     * since there are some tables that don't have body label cells.
     * Note: The row index of the table body restarts at zero.
     * @param int $offset index of the JSON-stat value array
     * @param int $rowIdx row index
     * @return void
     */
    public function addFirstCellBody(int $offset, int $rowIdx): void;

    /**
     * Add a label cell to a row of the table header.
     * @param int $dimIdx dimension index
     * @param int $rowIdx row index
     * @return void
     */
    public function addLabelCellHeader(int $dimIdx, int $rowIdx): void;

    /**
     * Add a label cell to the row of the table body.
     * Note: The row index of the table body restarts at zero
     * @param int $dimIdx dimension index
     * @param int $rowIdx row index
     * @return void
     */
    public function addLabelCellBody(int $dimIdx, int $rowIdx): void;

    /**
     * Add a value cell to a row of the table header.
     * @param int $offset index of the JSON-stat value array
     * @param int $rowIdx row index
     * @return void
     */
    public function addValueCellHeader(int $offset, int $rowIdx): void;

    /**
     * Add a value cell to a row of the table body.
     * Note: The row index of the table body restarts at zero
     * @param int $offset index of the JSON-stat value array
     * @param int $rowIdx row index
     * @return void
     */
    public function addValueCellBody(int $offset, int $rowIdx): void;

    /**
     * Add the last cell to a row of the table header.
     * @param int $offset index of the JSON-stat value array
     * @param int $rowIdx row index
     * @return void
     */
    public function addLastCellHeader(int $offset, int $rowIdx): void;

    /**
     * Add the last cell to a row of the table body.
     * Note: the row index of the table body restarts at zero
     * @param int $offset index of the JSON-stat value array
     * @param int $rowIdx row index
     * @return void
     */
    public function addLastCellBody(int $offset, int $rowIdx): void;
}