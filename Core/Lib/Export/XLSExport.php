<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2018 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Core\Lib\Export;

use FacturaScripts\Core\Base;
use Symfony\Component\HttpFoundation\Response;

/**
 * XLS export data.
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class XLSExport implements ExportInterface
{

    const LIST_LIMIT = 1000;

    /**
     * Translator object
     *
     * @var Base\Translator
     */
    private $i18n;

    /**
     * Class with number tools (to format numbers)
     *
     * @var Base\NumberTools
     */
    private $numberTools;

    /**
     * XLSX object.
     *
     * @var \XLSXWriter
     */
    private $writer;

    /**
     * PDFExport constructor.
     */
    public function __construct()
    {
        $this->i18n = new Base\Translator();
        $this->numberTools = new Base\NumberTools();
    }

    /**
     * Adds a new page with the document data.
     *
     * @param mixed $model
     */
    public function generateBusinessDocPage($model)
    {
        $headers = [
            'reference' => $this->i18n->trans('reference'),
            'description' => $this->i18n->trans('description'),
            'quantity' => $this->i18n->trans('quantity'),
            'price' => $this->i18n->trans('price'),
            'discount' => $this->i18n->trans('discount'),
            'tax' => $this->i18n->trans('tax'),
            'total' => $this->i18n->trans('total'),
        ];
        $tableData = [];
        foreach ($model->getlines() as $line) {
            $tableData[] = [
                'reference' => Base\Utils::fixHtml($line->referencia),
                'description' => Base\Utils::fixHtml($line->descripcion),
                'quantity' => $this->numberTools->format($line->cantidad),
                'price' => $this->numberTools->format($line->pvpunitario),
                'discount' => $this->numberTools->format($line->dtopor),
                'tax' => $this->numberTools->format($line->iva),
                'total' => $this->numberTools->format($line->pvptotal),
            ];
        }
        $this->generateTablePage($headers, $tableData);
        $this->generateModelPage($model, [], 'doc');
    }

    /**
     * Adds a new page with a table listing all models data.
     *
     * @param mixed                         $model
     * @param Base\DataBase\DataBaseWhere[] $where
     * @param array                         $order
     * @param int                           $offset
     * @param array                         $columns
     * @param string                        $title
     */
    public function generateListModelPage($model, $where, $order, $offset, $columns, $title = '')
    {
        /// Get the columns
        $tableCols = [];
        $sheetHeaders = [];
        $tableData = [];

        /// Get the columns
        $this->setTableColumns($columns, $tableCols, $sheetHeaders);

        $cursor = $model->all($where, $order, $offset, self::LIST_LIMIT);
        if (empty($cursor)) {
            $this->writer->writeSheet($tableData, $title, $sheetHeaders);
        }
        while (!empty($cursor)) {
            $tableData = $this->getTableData($cursor, $tableCols);
            $this->writer->writeSheet($tableData, $title, $sheetHeaders);

            /// Advance within the results
            $offset += self::LIST_LIMIT;
            $cursor = $model->all($where, $order, $offset, self::LIST_LIMIT);
        }
    }

    /**
     * Adds a new page with the model data.
     *
     * @param mixed  $model
     * @param array  $columns
     * @param string $title
     */
    public function generateModelPage($model, $columns, $title = '')
    {
        $tableData = [];
        foreach ((array) $model as $key => $value) {
            if (is_string($value)) {
                $tableData[] = ['key' => $key, 'value' => $value];
            }
        }

        $this->writer->writeSheet($tableData, $title, ['key' => 'string', 'value' => 'string']);
    }

    /**
     * Adds a new page with the table.
     *
     * @param array $headers
     * @param array $rows
     */
    public function generateTablePage($headers, $rows)
    {
        $this->writer->writeSheetRow('sheet1', $headers);

        foreach ($rows as $row) {
            $this->writer->writeSheetRow('sheet1', $row);
        }
    }

    /**
     * Return the full document.
     *
     * @return string
     */
    public function getDoc()
    {
        return (string) $this->writer->writeToString();
    }

    /**
     * Blank document.
     */
    public function newDoc()
    {
        $this->writer = new \XLSXWriter();
        $this->writer->setAuthor('FacturaScripts');
    }

    /**
     * Set headers and output document content to response.
     *
     * @param Response $response
     */
    public function show(Response &$response)
    {
        $response->headers->set('Content-Type', 'text/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment;filename=doc.xlsx');
        $response->setContent($this->getDoc());
    }

    /**
     * Returns the table data
     *
     * @param array $cursor
     * @param array $tableCols
     *
     * @return array
     */
    private function getTableData($cursor, $tableCols)
    {
        $tableData = [];

        /// Get the data
        foreach ($cursor as $key => $row) {
            foreach ($tableCols as $col) {
                if (!isset($row->{$col}) || null === $row->{$col}) {
                    $tableData[$key][$col] = '';
                    continue;
                }

                $tableData[$key][$col] = $row->{$col};
            }
        }

        return $tableData;
    }

    /**
     * Set the table content.
     *
     * @param $columns
     * @param $tableCols
     * @param $sheetHeaders
     */
    private function setTableColumns(&$columns, &$tableCols, &$sheetHeaders)
    {
        foreach ($columns as $col) {
            if (isset($col->columns)) {
                $this->setTableColumns($col->columns, $tableCols, $sheetHeaders);
                continue;
            }

            if (!$col->hidden()) {
                $tableCols[$col->widget->fieldname] = $col->widget->fieldname;
                $sheetHeaders[$col->widget->fieldname] = 'string';
            }
        }
    }
}
