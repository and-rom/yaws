<?php
    ini_set("precision", 14);
    ini_set("serialize_precision", -1);
    date_default_timezone_set('Europe/Moscow');

    if ($json = file_get_contents("php://input")) {
        if ($obj = json_decode($json, true)) {
            if(!file_exists("waterius.db")) {
                $db=new SQLite3("waterius.db");
                $sql="CREATE TABLE data (
                    id INTEGER PRIMARY KEY,
                    imp0 INTEGER,
                    imp1 INTEGER,
                    delta0 INTEGER,
                    delta1 INTEGER,
                    ch0 REAL,
                    ch1 REAL,
                    adc0 INTEGER,
                    adc1 INTEGER,
                    good INTEGER,
                    boot INTEGER,
                    version INTEGER,
                    version_esp TEXT,
                    key TEXT,
                    resets INTEGER,
                    email TEXT,
                    voltage REAL,
                    voltage_diff REAL,
                    voltage_low INTEGER,
                    f0 INTEGER,
                    f1 INTEGER,
                    rssi TEXT,
                    waketime INTEGER,
                    setuptime INTEGER,
                    period_min INTEGER,
                    serial0 TEXT,
                    serial1 TEXT,
                    model INTEGER,
                    datetime INTEGER)";
                $db->query($sql);
                $sql="CREATE TABLE meters (
                    id INTEGER PRIMARY KEY,
                    key TEXT,
                    name TEXT,
                    check0 INTEGER,
                    check1 INTEGER,
                    UNIQUE(key))";
                $db->query($sql);
            } else {
               $db = new SQLite3('waterius.db');
            }

            $sql="INSERT OR IGNORE INTO meters (key) VALUES('".$obj['key']."')";
            $db->query($sql);

            $sql = "INSERT INTO data (".implode(",", array_keys($obj)).", datetime) VALUES ('".implode("','",array_values($obj))."', strftime('%s','now'))";
            $db->query($sql);
        }
        exit;
    }

    $response = new stdClass;

    if (isset($_GET) && count($_GET)) {

        $action = (isset($_GET['action']) ? $_GET['action'] : "");

        switch ($action) {
            case "data":
                if(file_exists("waterius.db")) {
                    $db=new SQLite3("waterius.db");
                    $sql = "SELECT key, name, check0, check1 FROM meters";
                    $meters = $db->query($sql);
                    while($meter = $meters->fetchArray(SQLITE3_ASSOC)) {
                        $sql="SELECT
                                key,
                                ch0,
                                ch1,
                                delta0,
                                delta1,
                                serial0,
                                serial1,
                                voltage,
                                voltage_diff,
                                voltage_low,
                                rssi,
                                version,
                                version_esp,
                                datetime
                            FROM data
                            WHERE key = '".$meter['key']."'
                            ORDER BY id DESC LIMIT 1";
                        $data = $db->query($sql);
                        $data = $data->fetchArray(SQLITE3_ASSOC);
                        $data['name'] = $meter['name'] ? $meter['name'] : "";
                        $data['check0'] = $meter['check0'] ? $meter['check0'] : "";
                        $data['check1'] = $meter['check1'] ? $meter['check1'] : "";
                        $response->meters[] = $data;
                    }
                } else {
                    $response->meters[] = array (
                        "key" => "",
                        "ch0" => "0",
                        "ch1" => "0",
                        "delta0" => "0",
                        "delta1" => "0",
                        "serial0" => "",
                        "serial1" => "",
                        "voltage" => "",
                        "voltage_diff" => "",
                        "voltage_low" => "",
                        "rssi" => "",
                        "version" => "",
                        "version_esp" => "",
                        "datetime" => "0"
                    );
                }
                $response->code = 200;
                break;
            case "set":
                $data = $_GET['type'] == "date" ? strtotime($_GET['data']) : $_GET['data'];
                $db=new SQLite3("waterius.db");
                $sql = "UPDATE meters SET ".$_GET['field']." = '".$data."' WHERE key = '".$_GET['key']."'";
                $db->query($sql);
                $response->code = 200;
                break;
            default:
                $response->msg = "Method Not Allowed";
                $response->code = 405;
                break;
        }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response ,JSON_UNESCAPED_UNICODE);
    exit;
    }
?>
<!DOCTYPE html>
<html lang="ru" class="h-100">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta http-equiv="Cache-Control" content="max-age=3600, must-revalidate" />
    <meta name="robots" content="noindex, nofollow" />
    <meta name="description" content="" />
    <meta name="author" content="" />

    <title>Waterius</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/icons/speedometer.svg" rel="icon" integrity="sha256-/LtKZxiOPqtznsv147qz3xNwseQA3MAYfdH5jg1n3bc=" crossorigin="anonymous"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" integrity="sha256-uxjsS9cYFLTjmlr8j5i+DqvOLCBugRzEeWxUMWZeYXQ=" crossorigin="anonymous">
  </head>
  <body class="d-flex flex-column h-100">

    <nav class="navbar navbar-light mb-5 bg-light">
      <div class="container-fluid">
        <span class="navbar-brand mb-0 h1"><img src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/icons/speedometer.svg" alt="" width="30" height="24" class="d-inline-block align-text-top"> Waterius</span>
        <span class="navbar-text">
          <a class="" href="https://github.com/dontsovcmc/"><i class="bi bi-github"></i> waterius</a>
          <a class="" href="https://github.com/and-rom/yaws/"><i class="bi bi-github"></i> yaws</a>
        </span>
      </div>
    </nav>

    <div class="container">
      <div class="row justify-content-md-center">
        <div class="col-md-6">
          <div class="card text-center">
            <div class="card-header">
              <div><i class="bi bi-cpu-fill"></i> <?= $result['key']?></div>
              <div class="text-muted small"><?= date('d.m.Y H:i:s', $result['datetime']);?></div>
            </div>
            <div class="card-body">
              <div class="card-title border-bottom">
                <div class="row row-cols-2">
                  <div class="col">
                    <span class="text-danger fs-4 fw-bold"><i class="bi bi-droplet-fill"></i> <?= $result['ch0']?> м<sup>3</sup></span>
                  </div>
                  <div class="col">
                    <span class="text-primary fs-4 fw-bold"><i class="bi bi-droplet-fill"></i> <?= $result['ch1']?> м<sup>3</sup></span>
                  </div>
                  <div class="col">
                    <span class="text-secondary"><i class="bi bi-plus"></i> <?= $result['delta0']/1000?></span>
                  </div>
                  <div class="col">
                    <span class="text-secondary"><i class="bi bi-plus"></i> <?= $result['delta1']/1000?></span>
                  </div>
                  <div class="col">
                    <span class="text-secondary small"><i class="bi bi-patch-check-fill"></i> <!--{{ .Check1 }}--></span>
                  </div>
                  <div class="col">
                    <span class="text-secondary small"><i class="bi bi-patch-check-fill"></i> <!--{{ .Check1 }}--></span>
                  </div>
                  <div class="col">
                    <span class="text-muted small"><i class="bi bi-hash"></i> <?= $result['serial0']?></span>
                  </div>
                  <div class="col">
                    <span class="text-muted small"><i class="bi bi-hash"></i> <?= $result['serial1']?></span>
                  </div>
                </div>
              </div>
              <div class="card-text">
                <ul class="list-group list-group-flush">
                  <li class="list-group-item">
                    <span class="<?= (boolval($result['voltage_low']) ? 'text-warning' : 'text-success')?>">
                      <i class="bi <?= (boolval($result['voltage_low']) ? 'bi-battery' : 'bi-battery-half')?>"></i> <?= $result['voltage']?> В
                    </span>
                  </li>
                  <li class="list-group-item"><i class="bi bi-lightning-fill"></i> <?= $result['voltage_diff']?> мВ</li>
                  <li class="list-group-item">
                    <span class="<?= ($result['rssi'] < -65 ? "text-danger" : $result['rssi'] < -50 ? "text-warning" : "text-success" )?>">
                      <i class="bi bi-wifi"></i> <?= $result['rssi']?> dBm
                    </span>
                  </li>
                </ul>
              </div>
            </div>
            <div class="card-footer text-muted">
              ATtiny: v<?= $result['version']?> | ESP: v<?= $result['version_esp']?>
            </div>
          </div>

        </div>
      </div>
    </div>

    <footer class="footer mt-auto py-3 bg-light">
      <div class="container">
        <p class="text-center text-muted mb-0">© 2021 AndRom</p>
      </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js" integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous"></script>
  </body>
</html>
