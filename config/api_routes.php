<?php

declare(strict_types=1);

return [
    ['GET', '/api/v1', ['ApiV1Controller', 'index'], false],
    ['GET', '/api/v1/health', ['ApiV1Controller', 'health'], false],
    ['GET', '/api/v1/me', ['ApiV1Controller', 'me'], true],
    ['GET', '/api/v1/device-types', ['ApiV1Controller', 'deviceTypes'], true],
    ['GET', '/api/v1/companies', ['ApiV1Controller', 'companies'], true],
    ['POST', '/api/v1/companies', ['ApiV1Controller', 'createCompany'], true, true],
    ['GET', '/api/v1/companies/(?P<id>\d+)', ['ApiV1Controller', 'company'], true],
    ['PUT', '/api/v1/companies/(?P<id>\d+)', ['ApiV1Controller', 'updateCompany'], true, true],
    ['PATCH', '/api/v1/companies/(?P<id>\d+)', ['ApiV1Controller', 'updateCompany'], true, true],
    ['DELETE', '/api/v1/companies/(?P<id>\d+)', ['ApiV1Controller', 'deactivateCompany'], true, true],
    ['GET', '/api/v1/companies/(?P<id>\d+)/machines', ['ApiV1Controller', 'companyMachines'], true],
    ['POST', '/api/v1/companies/(?P<id>\d+)/machines', ['ApiV1Controller', 'createMachine'], true],
    ['GET', '/api/v1/machines/(?P<id>\d+)', ['ApiV1Controller', 'machine'], true],
    ['GET', '/api/v1/machines/(?P<id>\d+)/photos', ['ApiV1Controller', 'machinePhotos'], true],
];
