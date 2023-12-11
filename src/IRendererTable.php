<?php

namespace jsonstatPhpViz;

use DOMException;
use DOMNode;
use jsonstatPhpViz\Html\RendererCell;
use jsonstatPhpViz\Html\RendererTable;

interface IRendererTable
{
    /**
     * Instantiates the class.
     * @param Reader $jsonStatReader
     * @param int|null $numRowDim
     */
    public function __construct(Reader $jsonStatReader, ?int $numRowDim = null);

    /**
     * Set the number of dimensions to be used for rows.
     * @param int $numRowDim
     */
    public function setNumRowDim(int $numRowDim): void;

    /**
     * Renders the data as a html table.
     * Reads the value array and renders it as a table.
     * @return string csv
     */
    public function render(): string;

    /**
     * Returns the default number of dimensions used for rendering rows.
     * By default, a table is rendered using all dimensions for rows expect the last two dimensions are used for columns.
     * When there are fewer than 3 dimensions, only the first dimension is used for rows.
     * @return int
     */
    public function numRowDimAuto(): int;

    /**
     * Creates the internal structure of the table.
     * @return void
     */
    public function build(): void;

    /**
     * Automatically sets the caption.
     * Sets the caption from the optional JSON-stat label property. HTML from the JSON-stat is escaped.
     * @return void
     */
    public function initCaption(): void;

    /**
     * Instantiate the RendererCell class.
     * @return void
     */
    public function initRendererCell(): void;

    /**
     * Creates the table body and appends table cells row by row to it.
     */
    public function rows(): void;

    /**
     * Creates the table head and appends header cells, row by row to it.
     */
    public function headers(): void;

    /**
     * Creates and inserts a caption.
     */
    public function caption(): void;
}