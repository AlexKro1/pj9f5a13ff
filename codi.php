<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cpus = escapeshellarg($_POST["cpus"]);
    $ram = escapeshellarg($_POST["ram"]);
    $vm_name = escapeshellarg($_POST["vm_name"]);
    $hostname = escapeshellarg($_POST["hostname"]);
    $host_folder = escapeshellarg($_POST["host_folder"]);
    $vm_folder = escapeshellarg($_POST["vm_folder"]);
    $port_host = escapeshellarg($_POST["port_host"]);
    $port_vm = escapeshellarg($_POST["port_vm"]);
    $install_microk8s = isset($_POST["microk8s"]) && $_POST["microk8s"] === "yes";

    // Script de aprovisionamiento base
    $provision_script = <<<EOT
        sudo apt-get -y update
        sudo apt-get -y install net-tools whois aptitude git zip unzip
        sudo apt-get -y install apt-transport-https ca-certificates curl gnupg2 software-properties-common
        curl -fsSL https://download.docker.com/linux/debian/gpg | sudo apt-key add -
        sudo add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/debian \$(lsb_release -cs) stable"
        sudo apt-get update -y
        sudo apt-get -y install docker-ce docker-ce-cli containerd.io docker-compose
        sudo chown -R vagrant:vagrant /home/vagrant/pj9f4a86
        sudo gpasswd -a vagrant docker
EOT;

    // Agregar instalación de MicroK8s si se seleccionó
    if ($install_microk8s) {
        $provision_script .= <<<EOT

        # Instal·lació de MicroK8s
        sudo snap install microk8s --classic
        sudo usermod -a -G microk8s vagrant
        sudo mkdir -p /home/vagrant/.kube
        sudo chown -R vagrant:vagrant /home/vagrant/.kube
EOT;
    }

    // Generar el Vagrantfile
    $vagrantfile = <<<EOT
# -*- mode: ruby -*-
# vi: set ft=ruby :

# VARIABLES

BOX_IMAGE = "debian/bookworm64"
PROVIDER = "virtualbox"
NUM_CPUS = $cpus
MEMORIA_RAM = $ram
NOM_MAQUINA = $vm_name
HOSTNAME = $hostname
CARPETA_MAQ_FIS = $host_folder
CARPETA_MAQ_VIR = $vm_folder
PORT_VIR1 = $port_vm
PORT_FIS1 = $port_host
PROT = "tcp"

# CONFIGURACIÓ DE LA MÀQUINA

Vagrant.configure("2") do |config|
    # BOX
    config.vm.box = BOX_IMAGE

    # NOMBRE Y HOSTNAME
    config.vm.hostname = HOSTNAME

    # RECURSOS DE LA VM
    config.vm.provider PROVIDER do |vb|
        vb.memory = MEMORIA_RAM
        vb.cpus = NUM_CPUS
    end

    # SINCRONIZACIÓN DE CARPETAS
    config.vm.synced_folder CARPETA_MAQ_FIS, CARPETA_MAQ_VIR

    # REENVÍO DE PUERTOS
    config.vm.network "forwarded_port", guest: PORT_VIR1, host: PORT_FIS1, protocol: PROT

    # PROVISIONAMIENTO
    config.vm.provision "shell", inline: <<-SHELL
$provision_script
    SHELL
end
EOT;

    // Guardar el Vagrantfile
    file_put_contents("Vagrantfile", $vagrantfile);

    // Forzar la descarga del Vagrantfile
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="Vagrantfile"');
    readfile("Vagrantfile");

    echo "Vagrantfile generado exitosamente.";
}
?>
