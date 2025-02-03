<?php


namespace SwooleBase\Foundation\Interfaces;


interface Validator
{
    /**
     * list of fields converted to carbon.
     *
     * @return array
     */
    public static function carbonFields(): array;

    /**
     * Data will be validated.
     * @param array $data
     */
    public function setInput(array $data);

    /**
     * @return bool
     */
    public function authorize(): bool;

    /**
     * @return array
     */
    public function toArray(): array;

    /**
     * @param array $input
     * @return array
     */
    public function prepareInput(array $input): array;

    /**
     * @param array $input
     * @return array
     */
    public function rules(array $input): array;

    /**
     * @return array
     */
    public function attributes(): array;

    /**
     * @return void
     */
    public function throwJsonIfFailed();

    /**
     * Data is validated.
     *
     * @param array $filter
     * @return array
     */
    public function validated(array $filter = []): array;
}