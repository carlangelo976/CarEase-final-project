<?php

class Add_carease_contracts_and_valid_ids {

    private $_lava;
    protected $dbforge;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        $forge = $this->_lava->dbforge;

        $forge->add_column('carease_users', [
            'id_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => TRUE,
            ],
            'id_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => TRUE,
            ],
            'id_image' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => TRUE,
            ],
            'id_status' => [
                'type'       => 'ENUM',
                'constraint' => "'none','pending','verified','rejected'",
                'null'       => FALSE,
                'default'    => 'none',
            ],
            'id_notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => TRUE,
            ],
            'id_verified_at' => [
                'type' => 'DATETIME',
                'null' => TRUE,
            ],
        ]);

        $forge->add_column('carease_bookings', [
            'return_reminder_sent_at' => [
                'type' => 'DATETIME',
                'null' => TRUE,
            ],
            'overdue_notice_sent_at' => [
                'type' => 'DATETIME',
                'null' => TRUE,
            ],
        ]);

        if ($forge->table_exists('carease_contracts')) {
            return;
        }

        $forge->add_field([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => TRUE,
                'auto_increment' => TRUE,
            ],
            'booking_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => TRUE,
                'null'       => FALSE,
            ],
            'contract_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => FALSE,
            ],
            'customer_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => FALSE,
            ],
            'customer_email' => [
                'type'       => 'VARCHAR',
                'constraint' => 190,
                'null'       => FALSE,
            ],
            'customer_phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => FALSE,
            ],
            'car_description' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => FALSE,
            ],
            'pickup_date' => [
                'type' => 'DATE',
                'null' => FALSE,
            ],
            'return_date' => [
                'type' => 'DATE',
                'null' => FALSE,
            ],
            'total_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => FALSE,
            ],
            'terms' => [
                'type' => 'TEXT',
                'null' => FALSE,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => "'draft','sent','signed','cancelled'",
                'null'       => FALSE,
                'default'    => 'draft',
            ],
            'customer_signature' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => TRUE,
            ],
            'signed_at' => [
                'type' => 'DATETIME',
                'null' => TRUE,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => TRUE,
                'null'       => TRUE,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => FALSE,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => TRUE,
            ],
        ]);

        $forge->add_key('id', TRUE);
        $forge->add_key('booking_id', FALSE, TRUE, 'booking_id_unique');
        $forge->create_table('carease_contracts');
    }

    public function down()
    {
        $forge = $this->_lava->dbforge;

        $forge->drop_table('carease_contracts');

        foreach (['return_reminder_sent_at', 'overdue_notice_sent_at'] as $column) {
            $forge->drop_column('carease_bookings', $column);
        }

        foreach (['id_type', 'id_number', 'id_image', 'id_status', 'id_notes', 'id_verified_at'] as $column) {
            $forge->drop_column('carease_users', $column);
        }
    }
}
