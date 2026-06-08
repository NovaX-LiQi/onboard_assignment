<?php

namespace App\Dtos;

class FacebookInsightsRequestDTO
{
    //DTO 只要少写或类型不对，IDE 和 PHP 会立刻报错
    public function __construct(
        public string $provider,
        public string $level,
        public array $fields,
        public string $dateFrom,
        public string $dateTo,
    ) {}

    public static function make(array $data): self
    {
        return self::fromArray($data);
    }

    //蛇形命名转换为小驼峰的对象属性，并提供缺省默认值。
    public static function fromArray(array $data): self
    {
        return new self(
            provider: $data['provider'] ?? 'facebook',
            level: $data['level'] ?? 'account',
            fields: $data['fields'] ?? ['impressions','clicks','spend'],
            dateFrom: $data['date_from'],
            dateTo: $data['date_to'],
        );
    }

    //把 DTO 类序列化回数组
    //Laravel Queue 队列在分发时（Dispatch），不能很好地直接持久化复杂的自定义对象，更建议传原生数组。
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'level' => $this->level,
            'fields' => $this->fields,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
    }
}