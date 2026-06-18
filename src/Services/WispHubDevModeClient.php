<?php
namespace Services;

class WispHubDevModeClient extends WispHubClient
{
    private string $testServiceId = '902';
    private string $testCedula = 'V20788775';
    private string $testUsuario = 'onu_prueba_oficina@sitelco';

    public function getServiceProfile(string $serviceId): array
    {
        if ($serviceId !== $this->testServiceId) {
            return parent::getServiceProfile($serviceId);
        }
        return [
            'status' => 200,
            'data' => [
                'id_servicio'  => $serviceId,
                'usuario'      => $this->testUsuario,
                'nombre'       => 'Cliente OFICINA Prueba',
                'apellidos'    => 'Prueba',
                'email'        => 'cliente@test.com',
                'cedula'       => $this->testCedula,
                'direccion'    => 'Av. Principal, Edif. Test, Piso 1',
                'localidad'    => 'Barquisimeto',
                'telefono'     => '04241234567',
                'saldo'        => 15.00,
                'descuento'    => 0.00,
                'aplicar_mora' => false,
                'recargo_mora' => 0,
            ],
        ];
    }

    public function getServiceDetail(string $serviceId): array
    {
        if ($serviceId !== $this->testServiceId) {
            return parent::getServiceDetail($serviceId);
        }
        return [
            'status' => 200,
            'data' => [
                'id_servicio'     => $serviceId,
                'usuario_rb'      => $this->testUsuario,
                'estado'          => 'activo',
                'facturas_pagadas'=> true,
                'zona'            => ['id' => 1, 'nombre' => 'ZONA KM23'],
                'plan_internet'   => ['id' => 1, 'nombre' => 'Plan Básico 20MB'],
                'router'          => ['id' => 1, 'nombre' => 'Router Principal'],
                'ip'              => '192.168.1.100',
                'fecha_instalacion' => '2025-01-01T00:00:00Z',
            ],
        ];
    }

    public function getPendingInvoices(string $serviceId): array
    {
        if ($serviceId !== $this->testServiceId) {
            return parent::getPendingInvoices($serviceId);
        }

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $daysAgo5 = date('Y-m-d', strtotime('-5 days'));
        $daysAgo10 = date('Y-m-d', strtotime('-10 days'));
        $daysPlus3 = date('Y-m-d', strtotime('+3 days'));
        $daysPlus10 = date('Y-m-d', strtotime('+10 days'));

        return [
            [
                'id'               => 9701,
                'folio'            => 9701,
                'fecha_emision'    => $daysAgo10,
                'fecha_vencimiento'=> $daysAgo10,
                'fecha_pago'       => null,
                'estado'           => 'Pendiente de Pago',
                'tipo'             => 1,
                'total'            => 20.00,
                'monto_pendiente'  => 20.00,
                'sub_total'        => 20.00,
                'total_cobrado'    => 0,
                'articulos'        => [
                    ['id' => 1, 'descripcion' => 'Renta y mantenimiento de la red: ZONA KM23 - Junio 2026', 'precio' => '20.00', 'cantidad' => 1],
                ],
            ],
            [
                'id'               => 9702,
                'folio'            => 9702,
                'fecha_emision'    => $daysAgo5,
                'fecha_vencimiento'=> $yesterday,
                'fecha_pago'       => null,
                'estado'           => 'Pendiente de Pago',
                'tipo'             => 1,
                'total'            => 20.00,
                'monto_pendiente'  => 20.00,
                'sub_total'        => 20.00,
                'total_cobrado'    => 0,
                'articulos'        => [
                    ['id' => 2, 'descripcion' => 'Renta y mantenimiento de la red: ZONA KM23 - Julio 2026', 'precio' => '20.00', 'cantidad' => 1],
                ],
            ],
            [
                'id'               => 9703,
                'folio'            => 9703,
                'fecha_emision'    => $today,
                'fecha_vencimiento'=> $today,
                'fecha_pago'       => null,
                'estado'           => 'Pendiente de Pago',
                'tipo'             => 1,
                'total'            => 35.00,
                'monto_pendiente'  => 35.00,
                'sub_total'        => 35.00,
                'total_cobrado'    => 0,
                'articulos'        => [
                    ['id' => 3, 'descripcion' => 'Instalacion Equipo en COMODATO Vsol AX1500', 'precio' => '35.00', 'cantidad' => 1],
                ],
            ],
            [
                'id'               => 9704,
                'folio'            => 9704,
                'fecha_emision'    => $today,
                'fecha_vencimiento'=> $daysPlus3,
                'fecha_pago'       => null,
                'estado'           => 'Pendiente de Pago',
                'tipo'             => 1,
                'total'            => 20.00,
                'monto_pendiente'  => 20.00,
                'sub_total'        => 20.00,
                'total_cobrado'    => 0,
                'articulos'        => [
                    ['id' => 4, 'descripcion' => 'Renta y mantenimiento de la red: ZONA KM23 - Agosto 2026', 'precio' => '20.00', 'cantidad' => 1],
                ],
            ],
            [
                'id'               => 9705,
                'folio'            => 9705,
                'fecha_emision'    => $today,
                'fecha_vencimiento'=> $daysPlus10,
                'fecha_pago'       => null,
                'estado'           => 'Pendiente de Pago',
                'tipo'             => 1,
                'total'            => 20.00,
                'monto_pendiente'  => 20.00,
                'sub_total'        => 20.00,
                'total_cobrado'    => 0,
                'articulos'        => [
                    ['id' => 5, 'descripcion' => 'Renta y mantenimiento de la red: ZONA KM23 - Septiembre 2026', 'precio' => '20.00', 'cantidad' => 1],
                ],
            ],
            [
                'id'               => 9706,
                'folio'            => 9706,
                'fecha_emision'    => $daysAgo5,
                'fecha_vencimiento'=> $daysPlus3,
                'fecha_pago'       => null,
                'estado'           => 'Pendiente de Pago',
                'tipo'             => 1,
                'total'            => 50.00,
                'monto_pendiente'  => 30.00,
                'sub_total'        => 50.00,
                'total_cobrado'    => 20.00,
                'articulos'        => [
                    ['id' => 6, 'descripcion' => 'Instalacion de Red FTTH y Equipo ONT (Abonado \$20)', 'precio' => '50.00', 'cantidad' => 1],
                ],
            ],
        ];
    }

    public function getClientBalance(string $serviceId): float
    {
        if ($serviceId !== $this->testServiceId) {
            return parent::getClientBalance($serviceId);
        }
        return 15.00;
    }

    public function getLastPaidInvoice(string $usuario): ?array
    {
        if ($usuario !== $this->testUsuario) {
            return parent::getLastPaidInvoice($usuario);
        }
        return [
            'id_factura'       => 9003,
            'folio'            => 9003,
            'fecha_emision'    => date('Y-m-d', strtotime('-15 days')),
            'fecha_vencimiento'=> date('Y-m-d', strtotime('-10 days')),
            'fecha_pago'       => date('Y-m-d', strtotime('-10 days')) . 'T16:45:00Z',
            'estado'           => 'Pagada',
            'monto'            => 20.00,
            'total_cobrado'    => 20.00,
            'referencia'       => 'BDV345678',
        ];
    }

    public function getClientByDocument(string $document): array
    {
        $cleanDoc = preg_replace('/^[A-Z]/i', '', $document);
        if ($cleanDoc !== '20788775') {
            return parent::getClientByDocument($document);
        }
        return [
            'status' => 200,
            'data' => [
                'data' => [
                    'service_id' => $this->testServiceId,
                    'cedula'     => $this->testCedula,
                    'nombre'     => 'Cliente OFICINA Prueba',
                    'email'      => 'cliente@test.com',
                    'telefono'   => '04241234567',
                    'usuario'    => $this->testUsuario,
                ],
            ],
        ];
    }

    public function findClientByDocument(string $document, int $maxPages = 50): array
    {
        $cleanDoc = preg_replace('/^[A-Z]/i', '', $document);
        if ($cleanDoc !== '20788775') {
            return parent::findClientByDocument($document);
        }
        return [
            'status' => 200,
            'data' => [
                'current_page' => 1,
                'data' => [
                    [
                        'id'       => $this->testServiceId,
                        'nombre'   => 'Cliente OFICINA Prueba',
                        'cedula'   => $this->testCedula,
                        'estado'   => 'activo',
                        'usuario'  => $this->testUsuario,
                    ],
                ],
                'last_page' => 1,
                'total' => 1,
            ],
        ];
    }

    public function registerPaymentAndActivate(
        string $serviceId,
        float  $amount,
        string $reference,
        string $paymentDate,
        int    $formaPagoId = self::FORMA_PAGO_OPERACION_BANCARIA,
        bool   $forceActivate = false,
        string $cedula = '',
        array  $invoiceIds = []
    ): array {
        if ($serviceId !== $this->testServiceId) {
            return parent::registerPaymentAndActivate($serviceId, $amount, $reference, $paymentDate, $formaPagoId, $forceActivate, $cedula, $invoiceIds);
        }
        return [
            'status' => 200,
            'message' => 'Pago registrado correctamente (DEV MODE)',
            'task_id' => 'dev-mode-task-' . uniqid(),
            'amount_applied' => $amount,
            'payments_registered' => [],
        ];
    }
}
