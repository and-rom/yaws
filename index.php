<?php
    define("WATERIUS_RU", false);

    if (php_sapi_name() == "cli") {
        $stdin = fopen("php://stdin", "r");
        echo "Login: ";
        $login = rtrim(fgets($stdin));
        echo "Password: ";
        $password = password_hash(rtrim(fgets($stdin)), PASSWORD_DEFAULT);
        file_put_contents("./.htpassword", $login.":".$password);
        exit;
    }

    ini_set("precision", 14);
    ini_set("serialize_precision", -1);
    date_default_timezone_set("Europe/Moscow");

    if ($_SERVER["REQUEST_METHOD"] == "POST" && !filter_var($_SERVER["REMOTE_ADDR"], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
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
                   $db = new SQLite3("waterius.db");
                }

                $sql="INSERT OR IGNORE INTO meters (key) VALUES('".$obj["key"]."')";
                $db->query($sql);

                $sql = "INSERT INTO data (".implode(",", array_keys($obj)).", datetime) VALUES ('".implode("','",array_values($obj))."', strftime('%s','now'))";
                $db->query($sql);

                if (WATERIUS_RU && file_exists("waterius.ru.cer"))
                file_get_contents("https://cloud.waterius.ru/", false, stream_context_create(array(
                    "ssl" => [
                        "cafile" => "waterius.ru.cer",
                        "verify_peer"=> true,
                        "verify_peer_name"=> true
                    ],
                    "http" => array(
                        "method"  => "POST",
                        "content" => $json,
                        "header"=>  "Content-Type: application/json\r\n" .
                                    "Accept: application/json\r\n",
                        "ignore_errors" => true
                ))));

            }
            exit;
        }
    } elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
        header('HTTP/1.1 406 Not Acceptable');
        exit;
    }

    list($login, $password) = explode(":", file_get_contents("./.htpassword"), 2);
    if (isset($_SERVER["PHP_AUTH_USER"]) && password_verify($_SERVER["PHP_AUTH_PW"], $password) && $_SERVER["PHP_AUTH_USER"]==$login) {
        if (isset($_GET) && count($_GET)) {

            $response = new stdClass;

            $action = (isset($_GET["action"]) ? $_GET["action"] : "");

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
                                WHERE key = '".$meter["key"]."'
                                ORDER BY id DESC LIMIT 1";
                            $data = $db->query($sql);
                            $data = $data->fetchArray(SQLITE3_ASSOC);
                            $data["name"] = $meter["name"] ? $meter["name"] : "";
                            $data["check0"] = $meter["check0"] ? $meter["check0"] : "";
                            $data["check1"] = $meter["check1"] ? $meter["check1"] : "";
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
                    $data = $_GET["type"] == "date" ? strtotime($_GET["data"]) : $_GET["data"];
                    $db=new SQLite3("waterius.db");
                    $sql = "UPDATE meters SET ".$_GET["field"]." = '".$data."' WHERE key = '".$_GET["key"]."'";
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
    } else {
        header('WWW-Authenticate: Basic realm="Protected"');
        header('HTTP/1.0 401 Unauthorized');
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

    <main>
      <nav class="navbar navbar-light mb-3 bg-light">
        <div class="container-fluid">
          <span class="navbar-brand mb-0 h1"><img src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/icons/speedometer.svg" alt="" width="30" height="24" class="d-inline-block align-text-top"> Waterius</span>
          <span class="navbar-text">
            <a class="" href="https://github.com/dontsovcmc/waterius/" target="_blank"><i class="bi bi-github"></i> waterius</a>
            <a class="" href="https://github.com/and-rom/yaws/" target="_blank"><i class="bi bi-github"></i> yaws</a>
          </span>
        </div>
      </nav>

      <div id="main-container" class="container">
        <div id="meter-template" class="meter-container row justify-content-md-center mb-3" style="display:none">
          <div class="col-md-6">
            <div class="card text-center">
              <div class="card-header">
                <div class="meter-name-container">
                  <i class="bi bi-cpu-fill"></i> <span class="meter-name">{{ .key || .name }}</span>
                  <a class="edit-btn link-secondary" href="#"><i class="bi bi-pencil"></i></a>
                </div>
                <form class="meter-name-edit row g-1 align-items-center" style="display:none">
                  <div class="col-10">
                    <div class="input-group">
                      <div class="input-group-text"><i class="bi bi-cpu-fill"></i></div>
                      <input type="text" class="form-control" class="meter-name-edit-name" name="data" placeholder="" autocomplete="off" />
                    </div>
                  </div>
                  <div class="col-2">
                    <button type="submit" class="btn btn-primary">OK</button>
                  </div>
                <input type="hidden" name="field" value="name">
                </form>
                <div class="text-muted small">
                  <span class="meter-date">{{ date('d.m.Y H:i:s', .datetime) }}</span>
                  <a class="chart-btn link-secondary" href="#" data-bs-toggle="modal" data-bs-target="#chart-modal" data-bs-meter-key="{{ .key }}"><i class="bi bi-graph-up"></i></a>
                </div>
              </div>
              <div class="card-body">
                <div class="card-title border-bottom">
                  <div class="row row-cols-2">
                    <div class="col">
                      <span class="text-danger fs-4 fw-bold"><i class="bi bi-droplet-fill"></i> <span class="meter-ch0">{{ .ch0 }}</span> м<sup>3</sup></span>
                    </div>
                    <div class="col">
                      <span class="text-primary fs-4 fw-bold"><i class="bi bi-droplet-fill"></i> <span class="meter-ch1">{{ .ch1 }}</span> м<sup>3</sup></span>
                    </div>
                    <div class="col">
                      <span class="text-secondary"><i class="bi bi-plus"></i> <span class="meter-delta0">{{ .delta0 }}</span></span>
                    </div>
                    <div class="col">
                      <span class="text-secondary"><i class="bi bi-plus"></i> <span class="meter-delta1">{{ .delta1 }}</span></span>
                    </div>
                    <div class="col">
                      <span class="text-secondary small">
                        <i class="meter-check0-color bi bi-patch-check-fill"></i> <span class="meter-check0-color meter-check0">{{ .check0 }}</span>
                        <a class="edit-btn link-secondary" href="#"><i class="bi bi-pencil"></i></a>
                      </span>
                      <form class="meter-check0-edit row g-1 align-items-center" style="display:none">
                        <div class="col-10">
                          <div class="input-group">
                            <div class="input-group-text"><i class="bi bi-patch-check-fill"></i></div>
                            <input type="date" class="form-control" class="meter-check0-edit-check0" name="data" placeholder="" autocomplete="off" />
                          </div>
                        </div>
                        <div class="col-2">
                          <button type="submit" class="btn btn-primary">OK</button>
                        </div>
                      <input type="hidden" name="field" value="check0">
                      </form>
                    </div>
                    <div class="col">
                      <span class="text-secondary small">
                        <i class="meter-check1-color bi bi-patch-check-fill"></i> <span class="meter-check1-color meter-check1">{{ .check1 }}</span>
                        <a class="edit-btn link-secondary" href="#"><i class="bi bi-pencil"></i></a>
                      </span>
                      <form class="meter-check1-edit row g-1 align-items-center" style="display:none">
                        <div class="col-10">
                          <div class="input-group">
                            <div class="input-group-text"><i class="bi bi-patch-check-fill"></i></div>
                            <input type="date" class="form-control" class="meter-check1-edit-check0" name="data" placeholder="" autocomplete="off" />
                          </div>
                        </div>
                        <div class="col-2">
                          <button type="submit" class="btn btn-primary">OK</button>
                        </div>
                      <input type="hidden" name="field" value="check1">
                      </form>
                    </div>
                    <div class="col">
                      <span class="text-muted small"><i class="bi bi-hash"></i> <span class="meter-serial0">{{ .serial0 }}</span></span>
                    </div>
                    <div class="col">
                      <span class="text-muted small"><i class="bi bi-hash"></i> <span class="meter-serial1">{{ .serial1 }}</span></span>
                    </div>
                  </div>
                </div>
                <div class="card-text">
                  <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                      <span class="meter-voltage-color">
                        <i class="meter-voltage-icon" class="bi"></i> <span class="meter-voltage">{{ .voltage }}</span> В
                      </span>
                    </li>
                    <li class="list-group-item"><i class="bi bi-lightning-fill"></i> <span class="meter-voltage_diff">{{ .voltage_diff }}</span> мВ</li>
                    <li class="list-group-item">
                      <span class="meter-rssi-color">
                        <i class="meter-rssi-icon" class="bi"></i> <span class="meter-rssi">{{ .rssi }}</span> dBm
                      </span>
                    </li>
                  </ul>
                </div>
              </div>
              <div class="card-footer text-muted">
                ATtiny: v<span class="meter-version">{{ .version }}</span> | ESP: v<span class="meter-version_esp">{{ .version_esp }}</span>
              </div>
            </div>

          </div>
        </div>
      </div>

      <div class="modal fade" id="chart-modal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="chart-modal-title"></h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div id="chart" class="modal-body"></div>
          </div>
        </div>
      </div>

    </main>

    <footer class="footer mt-auto py-3 bg-light">
      <div class="container">
        <p class="text-center text-muted mb-0">© 2021 AndRom</p>
      </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js" integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous"></script>
    <script type="text/javascript">
        var app = {
            update: function () {
                $.ajax({
                    dataType: "json",
                    url: "./",
                    async: true,
                    data: {
                        action: "data"
                    },
                    context: this,
                    success: this.display,
                    error: this.error
                });
            },
            display: function (data) {
                switch (data.code) {
                case 200:
                    if (data.meters.length != 0) {
                        data.meters.forEach((meter) => {
                            var meterContainer = $("#meter-template").clone()
                            $(meterContainer).attr("id", meter.key);
                            $(meterContainer).show();
                            $(".meter-name", meterContainer).html(meter.name != "" ? meter.name : meter.key);
                            $(".meter-date", meterContainer).html(this.dateTime(meter.datetime));
                            $(".chart-btn", meterContainer).data("bs-meter-key", meter.key);
                            $(".meter-ch0", meterContainer).html(meter.ch0);
                            $(".meter-ch1", meterContainer).html(meter.ch1);
                            $(".meter-delta0", meterContainer).html(meter.delta0/1000);
                            $(".meter-delta1", meterContainer).html(meter.delta1/1000);
                            $(".meter-check0", meterContainer).html(meter.check0 != "" ? this.dateTime(meter.check0, {day: "2-digit", month: "2-digit", year: "numeric"}) : "");
                            $(".meter-check0-color", meterContainer).addClass(meter.check0 && meter.check0*1000 <= +new Date() ? "text-danger" : meter.check0 && (meter.check0*1000 - +new Date())/86400000 < 180 ? "text-warning" : "");
                            $(".meter-check1", meterContainer).html(meter.check1 != "" ? this.dateTime(meter.check1, {day: "2-digit", month: "2-digit", year: "numeric"}) : "");
                            $(".meter-check1-color", meterContainer).addClass(meter.check1 && meter.check1*1000 <= +new Date() ? "text-danger" : meter.check1 && (meter.check1*1000 - +new Date())/86400000 < 180 ? "text-warning" : "");
                            $(".meter-serial0", meterContainer).html(meter.serial0);
                            $(".meter-serial1", meterContainer).html(meter.serial1);
                            $(".meter-voltage", meterContainer).html(meter.voltage);
                            $(".meter-voltage-color", meterContainer).addClass(meter.voltage_low == "1" ? "text-warning" : "text-success");
                            $(".meter-voltage-icon", meterContainer).addClass(meter.voltage_low == "1" ? "bi-battery" : "bi-battery-half");
                            $(".meter-voltage_diff", meterContainer).html(meter.voltage_diff);
                            $(".meter-rssi", meterContainer).html(meter.rssi);
                            $(".meter-rssi-color", meterContainer).addClass(meter.rssi < -65 ? "text-danger" : meter.rssi < -50 ? "text-warning" : "text-success");
                            $(".meter-rssi-icon", meterContainer).addClass(meter.rssi < -65 ? "bi-wifi-1" : meter.rssi < -50 ? "bi-wifi-2" : "bi-wifi");
                            $(".meter-version", meterContainer).html(meter.version);
                            $(".meter-version_esp", meterContainer).html(meter.version_esp);
                            $(meterContainer).appendTo("#main-container")
                        });
                        $(".edit-btn").on("click", this.edit.bind(this));
                        $("form").submit(this.set.bind(this));
                    }
                    $("#chart-modal").on("show.bs.modal", this.chartModal.bind(this));
                    break;
                default:
                    console.log(data.msg);
                break;
                }
            },
            edit: function (e) {
                $(e.currentTarget).parent().hide();
                $(e.currentTarget).parent().parent().children("form").show();
            },
            set: function (e) {
                e.preventDefault();
                var val = $("input", e.currentTarget).val()
                if ($("input", e.currentTarget).val()) {
                    if ($("input", e.currentTarget).attr("type") == "date") {
                        var type = "date";
                        var pubDate = new Date(val);
                        val = pubDate.toLocaleDateString();
                    } else {
                        var type = "text"
                    }
                    $(e.currentTarget).prev().children("span").html(val);
                    $.ajax({
                        url: "./",
                        async: true,
                        data: $(e.currentTarget).serializeArray().concat(
                            {name: "type", value: type},
                            {name: "action", value: "set"},
                            {name: "key", value: $(e.currentTarget).closest(".meter-container")[0].id}
                        ),
                        success: null
                    });
                }
                $(e.currentTarget).hide();
                $(e.currentTarget).prev().show();
            },
            chartModal: function (e) {
                if ($("#chart-modal-title").html() != "") return;
                $("#chart-modal-title").html($(e.relatedTarget).parents(".card-header").children(".meter-name-container").children(".meter-name").html());
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log("Update error!");
            },
            dateTime: function (timestamp, options = {}) {
                var pubDate = new Date(timestamp * 1000);

                return pubDate.toLocaleString(navigator.languages != undefined ? navigator.languages[0] : navigator.language, options);
            }
        }

        $(document).ready(app.update());
    </script>
  </body>
</html>
