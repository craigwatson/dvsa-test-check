lock '3.4.0'

require 'sshkit/sudo'

set :application, 'puppet'
set :repo_url, 'https://github.com/craigwatson/bitcoind-status.git'
set :deploy_to, '/opt/bitcoind-status'
set :ssh_options, {:forward_agent => true, keys: %w(~/.ssh/keys/vikingserv)}
set :linked_files, %w{php/config.php php/google_analytics.inc address}
set :scm, :git
set :log_level, :info
set :pty, false
set :keep_releases, 3

namespace :deploy do
  task :clear_cache do
    on roles(:app) do
      execute :sudo, "rm /var/tmp/bitcoind-status.cache"
    end
  end

  task :apache_restart do
    on roles(:app) do
      execute :sudo, "/etc/init.d/apache2 restart"
    end
  end

  task :symlink_wellknown do
    on roles(:app) do
      execute :sudo, "ln -s #{shared_path}/.well-known #{release_path}"
    end
  end
end

after "deploy:published", "deploy:symlink_wellknown"
after "deploy:symlink_wellknown","deploy:apache_restart"
