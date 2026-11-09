<?php

class Add_booking_locations {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        $forge = $this->_lava->dbforge;

        $forge->add_column('carease_bookings', [
            'pickup_location' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => TRUE,
            ],
            'pickup_lat' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,7',
                'null'       => TRUE,
            ],
            'pickup_lng' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,7',
                'null'       => TRUE,
            ],
            'dropoff_location' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => TRUE,
            ],
            'dropoff_lat' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,7',
                'null'       => TRUE,
            ],
            'dropoff_lng' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,7',
                'null'       => TRUE,
            ],
            'route_distance_m' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => TRUE,
            ],
            'route_duration_s' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => TRUE,
            ],
        ]);
    }

    public function down()
    {
        $forge = $this->_lava->dbforge;

        foreach ([
            'pickup_location',
            'pickup_lat',
            'pickup_lng',
            'dropoff_location',
            'dropoff_lat',
            'dropoff_lng',
            'route_distance_m',
            'route_duration_s',
        ] as $column) {
            $forge->drop_column('carease_bookings', $column);
        }
    }
}
