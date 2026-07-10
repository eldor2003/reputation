<?php

namespace App\DTO;

readonly class Brand24ProjectsListDTO
{
    /**
     * @param  list<Brand24ProjectDTO>  $projects
     */
    public function __construct(
        public array $projects,
    ) {}
}
