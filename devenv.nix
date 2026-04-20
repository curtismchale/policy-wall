{ pkgs, ... }:

{
  packages = with pkgs; [
    php83Packages.composer
  ];

  languages.php.enable  = true;
  languages.php.package = pkgs.php83.buildEnv {
    extensions = ({ all, enabled }: with all; enabled ++ [ mbstring ]);
    extraConfig = ''
      memory_limit = -1
    '';
  };

  enterShell = ''
    if [ ! -d vendor ]; then
      echo "Running composer install..."
      composer install --no-interaction
    fi

    echo ""
    echo "Policy Wall dev environment"
    echo "  composer test     — run PHPUnit"
    echo "  composer install  — install/update dev dependencies"
    echo ""
  '';
}
