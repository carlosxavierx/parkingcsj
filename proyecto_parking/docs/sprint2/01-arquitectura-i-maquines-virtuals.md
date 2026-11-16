# Sprint 2 — Arquitectura i màquines virtuals

## Sistema de Reserves de Places de Pàrquing · CSJ Parkings (Tarragona)

---

## 1. Arquitectura general

```mermaid
flowchart LR
    U["💻 Navegador de l'usuari<br/>https://csjparkings.local"]
    subgraph HOST["Equip amfitrió · VirtualBox · xarxa sols amfitrió 192.168.56.0/24"]
        subgraph SRV["parking-srv · 192.168.56.30"]
            A["Apache2 + PHP 8.1<br/>HTTPS 443 (SSL)"]
            P["phpMyAdmin"]
            M[("MariaDB<br/>csj_parkings")]
            R["RAID 1 (mdadm)<br/>/srv"]
            NE["node_exporter · mysqld_exporter"]
            A --> M
            P --> M
            M -.-> R
            A -.-> R
        end
        subgraph MON["parking-mon · 192.168.56.31"]
            PR["Prometheus"]
            G["Grafana"]
            BK[("Còpies de seguretat")]
        end
    end
    U -- "HTTPS" --> A
    PR -- "scrape" --> NE
    PR -- "blackbox HTTPS" --> A
    G --> PR
    SRV -- "rsync per SSH (cron)" --> BK
```

**Per què dos servidors?** L'enunciat recomana posar el monitoratge *en un servidor a part*: si
el servidor web cau, el servidor de monitoratge ho continua detectant. A més, guardar les còpies
de seguretat en una màquina diferent evita perdre-les si es perd `parking-srv`.

---

## 2. Màquines virtuals

| | **parking-srv** | **parking-mon** |
|---|---|---|
| Funció | Web (Apache2 + PHP), BD (MariaDB + phpMyAdmin), SSL, tallafocs, RAID 1, origen de les còpies | Monitoratge (Prometheus + Grafana) i destí de les còpies de seguretat |
| Sistema operatiu | Ubuntu Server 22.04 LTS | Ubuntu Server 22.04 LTS |
| vCPU | 2 | 1 |
| RAM | 1536 MB | 1024 MB |
| Discos | `sda` 20 GB (sistema) · `sdb` 5 GB + `sdc` 5 GB (RAID 1) | `sda` 15 GB |
| Adaptador 1 | NAT (accés a Internet per instal·lar paquets) | NAT |
| Adaptador 2 | Sols amfitrió · `enp0s8` · **192.168.56.30/24** | Sols amfitrió · `enp0s8` · **192.168.56.31/24** |
| Nom de host | `parking-srv` | `parking-mon` |

### 2.1 Justificació del dimensionament

L'equip amfitrió de desenvolupament té **8 GB de RAM i 4 fils de CPU**. Windows, l'editor i el
navegador ja n'ocupen uns 4–5 GB, per tant el pressupost per a les VM és d'uns **2,5 GB**:

- `parking-srv` (1,5 GB): Apache + PHP + MariaDB amb un volum de dades petit funcionen
  amb menys d'1 GB; la resta és marge per a la memòria cau de MariaDB i el sistema.
- `parking-mon` (1 GB): Prometheus i Grafana amb pocs *targets* i retenció curta (15 dies)
  ocupen uns 300–400 MB.
- Les VM s'executen **sense interfície gràfica** (*headless*) i s'administren per **SSH**
  des del terminal de l'equip amfitrió, per estalviar memòria.
- Els discos són **VDI d'assignació dinàmica**: només ocupen a l'amfitrió l'espai realment usat.
- No s'inclou un segon servidor web (balanceig de càrrega, opcional) perquè no hi cap en 8 GB.
  Es podrà afegir a l'equip de l'institut (16 GB), vegeu el punt 5.

### 2.2 Pla d'adreçament

| Equip | Interfície | Adreça |
|---|---|---|
| Amfitrió (Windows) | VirtualBox Host-Only Adapter | 192.168.56.1 |
| parking-srv | enp0s8 | 192.168.56.30 |
| parking-mon | enp0s8 | 192.168.56.31 |
| *(reservada)* parking-web2 | enp0s8 | 192.168.56.32 |

El servidor DHCP de la xarxa sols amfitrió està **desactivat**: totes les adreces són estàtiques.
Al fitxer `C:\Windows\System32\drivers\etc\hosts` de l'amfitrió s'afegeix:

```
192.168.56.30   csjparkings.local   www.csjparkings.local
192.168.56.31   monitor.csjparkings.local
```

---

## 3. Creació de les màquines a VirtualBox

> 📸 *Afegir captura de cada pas indicat amb [CAPTURA].*

### 3.1 Xarxa sols amfitrió (una sola vegada)

1. **Fitxer → Eines → Gestor de xarxes → Xarxes sols amfitrió.**
2. Comprovar que existeix l'adaptador amb `192.168.56.1/24` (si no, crear-lo).
3. Pestanya **Servidor DHCP** → desmarcar *Habilita el servidor*. **[CAPTURA]**

### 3.2 parking-srv

1. **Nova** → Nom `parking-srv`, imatge ISO `ubuntu-22.04-live-server-amd64.iso`,
   marcar **Omet la instal·lació desatesa**. **[CAPTURA]**
2. Maquinari: 1536 MB de memòria, 2 CPU. **[CAPTURA]**
3. Disc dur: crear un disc virtual de 20 GB, VDI, dinàmic.
4. **Configuració → Emmagatzematge → Controlador SATA → Afegeix disc dur → Crea**:
   `parking-raid1.vdi` (5 GB) i `parking-raid2.vdi` (5 GB). **[CAPTURA]**
5. **Configuració → Xarxa**: Adaptador 1 = NAT; Adaptador 2 = *Adaptador sols amfitrió*. **[CAPTURA]**
6. Instal·lació d'Ubuntu Server (opció **Try or Install Ubuntu Server** del GRUB):

   ![Menú d'arrencada de la ISO](img/srv-01-grub.png)

   - **Xarxa:** `enp0s3` per DHCP (NAT, 10.0.2.15); `enp0s8` → *Edit IPv4* → **Manual**,
     subxarxa `192.168.56.0/24`, adreça `192.168.56.30`, sense porta d'enllaç ni DNS
     (la sortida a Internet es fa per la interfície NAT).

     ![Configuració manual d'enp0s8](img/srv-02-xarxa-enp0s8-manual.png)
     ![Resum de la xarxa: enp0s3 per DHCP i enp0s8 estàtica](img/srv-03-xarxa-resum.png)

   - **Rèplica (mirror):** la proposada per defecte, `es.archive.ubuntu.com`.

     ![Mirror d'Ubuntu](img/srv-04-mirror.png)

   - **Emmagatzematge** (*Custom storage layout*): instal·lar **només al disc de 20 GB**.
     Els dos discos de 5 GB es deixen sense tocar perquè es faran servir per al RAID 1 amb `mdadm`.
     1. Disc de 20 GB → *Use As Boot Device* (crea la partició `bios_grub` d'1 MB).
     2. Espai lliure → *Add GPT Partition* → tota la mida, `ext4`, muntatge `/`.

     ![Disc de 20 GB com a dispositiu d'arrencada](img/srv-05-disc-arrencada.png)
     ![Partició bios_grub creada](img/srv-06-particio-bios-grub.png)
     ![Afegir partició GPT a l'espai lliure](img/srv-07-afegir-particio.png)
     ![Partició arrel ext4 de 20 GB](img/srv-08-particio-arrel-ext4.png)
     ![Resum: els discos de 5 GB queden lliures per al RAID](img/srv-09-resum-emmagatzematge.png)
     ![Confirmació abans de formatar](img/srv-10-confirmacio.png)

   - Perfil: usuari `carlos`, nom del servidor `parking-srv`; marcar **Install OpenSSH server**; cap *snap*.

     ![Instal·lació completada (inclou openssh-server)](img/srv-11-installacio-completa.png)

7. Comprovació després del primer arrencada: nom del servidor, usuari, xarxa, discos i servei SSH.

   ![hostnamectl i whoami: parking-srv, Ubuntu 22.04.5 LTS, usuari carlos](img/srv-12-hostnamectl-whoami.png)
   ![ip -br a, lsblk i systemctl status ssh](img/srv-13-xarxa-discos-ssh.png)

   - `enp0s3` = 10.0.2.15/24 (NAT) i `enp0s8` = 192.168.56.30/24 (sols amfitrió).
   - `sda` (20 GB) amb el sistema; `sdb` i `sdc` (5 GB) buits, preparats per al RAID 1.
   - `ssh.service` actiu i escoltant al port 22.

8. Quan acaba: `sudo poweroff` i iniciar amb **Inici sense interfície**.

### 3.3 parking-mon

Igual que l'anterior amb: 1024 MB, 1 CPU, un únic disc de 15 GB, IP `192.168.56.31` i nom `parking-mon`.

1. **Xarxa a VirtualBox:** Adaptador 1 = NAT; Adaptador 2 = Adaptador sols amfitrió
   (*VirtualBox Host-Only Ethernet Adapter*, el mateix que `parking-srv`).

   ![Adaptador 1: NAT](img/mon-01-adaptador1-nat.png)
   ![Adaptador 2: sols amfitrió](img/mon-02-adaptador2-sols-amfitrio.png)

2. **Xarxa a l'instal·lador:** `enp0s3` per DHCP i `enp0s8` manual amb `192.168.56.31/24`.

   ![Configuració manual d'enp0s8](img/mon-03-xarxa-enp0s8-manual.png)
   ![Resum de la xarxa](img/mon-04-xarxa-resum.png)

3. **Emmagatzematge:** *Custom storage layout* (no *Use an entire disk*, perquè amb LVM l'instal·lador
   només assigna la meitat del disc al volum arrel). Disc de 15 GB com a dispositiu d'arrencada i una
   partició `ext4` muntada a `/` amb tot l'espai.

   ![Custom storage layout](img/mon-05-custom-storage.png)
   ![Disc de 15 GB com a dispositiu d'arrencada](img/mon-06-disc-arrencada.png)
   ![Partició arrel ext4](img/mon-07-particio-arrel-ext4.png)
   ![Resum de l'emmagatzematge](img/mon-08-resum-emmagatzematge.png)

4. **Perfil:** usuari `carlos`, nom del servidor `parking-mon`; **Install OpenSSH server**; cap *snap*.

   ![Perfil de parking-mon](img/mon-09-perfil.png)
   ![Instal·lació del servidor OpenSSH](img/mon-10-openssh.png)

   L'autenticació per contrasenya per SSH es deixa activada de moment; es desactivarà quan
   s'hagin configurat les claus SSH (apartat de securització).

> ⚠️ **Incidència resolta:** amb 1024 MB la pantalla de l'instal·lador es bloquejava a la
> configuració del perfil. L'instal·lador d'Ubuntu Server s'executa sencer a la RAM i, un cop
> confirmat el disc, ja comença a instal·lar en segon pla, de manera que 1 GB no és suficient.
> **Solució:** s'assigna temporalment **2048 MB** per a la instal·lació i, un cop instal·lat,
> es torna a deixar a **1024 MB**, suficient per al funcionament del servidor.

### 3.4 Comprovació

**parking-mon** després de la instal·lació:

![Instal·lació de parking-mon completada](img/mon-11-installacio-completa.png)
![hostnamectl, whoami, ip -br a i lsblk a parking-mon](img/mon-12-hostnamectl-xarxa-discos.png)
![Servei SSH actiu a parking-mon](img/mon-13-ssh-actiu.png)

- Nom `parking-mon`, Ubuntu 22.04.5 LTS, usuari `carlos`.
- `enp0s3` = 10.0.2.15/24 (NAT) i `enp0s8` = 192.168.56.31/24 (sols amfitrió).
- `sda` de 15 GB amb `sda2` muntada a `/`.

**Des de l'equip amfitrió** (terminal de VS Code), amb les dues VM iniciades sense interfície:

```powershell
ping 192.168.56.30
ping 192.168.56.31
ssh carlos@192.168.56.30
ssh carlos@192.168.56.31
```

![Ping a parking-srv i parking-mon: 0 % de pèrdua](img/host-01-ping.png)
![Connexió SSH a parking-srv](img/host-02-ssh-parking-srv.png)
![Connexió SSH a parking-mon (primera connexió: s'accepta l'empremta de la clau)](img/host-03-ssh-parking-mon.png)

Resultat: l'amfitrió arriba a les dues màquines per la xarxa sols amfitrió i s'hi pot connectar per SSH.
Les dues màquines tenen actualitzacions pendents, que s'aplicaran al primer pas de la instal·lació
(`02-installacio-servidors.md`, apartat 1). No s'actualitza a Ubuntu 24.04 (*do-release-upgrade*) perquè
la 22.04 és LTS amb suport fins al 2027 i és la versió amb què s'ha documentat tot el projecte.

---

## 4. Instal·lació següent

La instal·lació i configuració dels serveis (RAID, Apache + PHP, MariaDB + phpMyAdmin, SSL,
tallafocs, permisos, còpies de seguretat i monitoratge) es documenta a
`02-installacio-servidors.md`.

---

## 5. Migració a l'equip de l'institut (OVA)

Un cop el sistema funcioni, les dues màquines s'exportaran com a **OVA**
(**Fitxer → Exporta un servei virtualitzat**, format OVF 2.0) i s'importaran a l'equip de
l'institut (16 GB de RAM).

Aspectes a tenir en compte:

- A l'equip de l'institut **no tenim permisos d'administrador**. Importar l'OVA no en necessita,
  però **crear una xarxa sols amfitrió sí**. Si no existeix ja, l'alternativa és:
  - Adaptador 2 = **Xarxa interna** entre les dues VM (192.168.56.0/24 igual), i
  - Adaptador 1 = **NAT amb reenviament de ports**: `2222→22` i `8443→443` cap a `parking-srv`,
    `3000→3000` (Grafana) cap a `parking-mon`. L'aplicació s'obriria a `https://localhost:8443`.
- En importar, triar **Genera noves adreces MAC** i comprovar que les IP estàtiques es mantenen.
- Amb 16 GB es pot afegir `parking-web2` (192.168.56.32) i un balancejador **HAProxy**
  (part opcional de l'enunciat).
