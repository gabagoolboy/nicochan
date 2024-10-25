<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

class Filter
{
    private array $post;
    private FloodChecker $floodChecker;
    private FilterAction $filterAction;
    private array $conditions;

    public function __construct(array $conditions, array $post, FloodChecker $floodChecker, FilterAction $filterAction)
    {
        $this->conditions = $conditions;
        $this->post = $post;
        $this->floodChecker = $floodChecker;
        $this->filterAction = $filterAction;
    }

    public function check(): bool
    {
        foreach ($this->conditions as $condition => $value) {
            $negate = $condition[0] === '!';
            $condition = $negate ? substr($condition, 1) : $condition;

            if ($this->matchCondition($condition, $value) === $negate) {
                return false;
            }
        }
        return true;
    }

    private function matchCondition(string $condition, $value): bool
    {
        switch (strtolower($condition)) {
            case 'custom':
                return $this->matchCustom($value);
            case 'flood-match':
                return $this->floodChecker->match($value, $this->post);
            case 'flood-time':
                return $this->floodChecker->isFloodTime($value);
            case 'flood-count':
                return $this->floodChecker->isFloodCount($value, $this->conditions['flood-time'] ?? 0);
            case 'name':
            case 'trip':
            case 'email':
            case 'subject':
            case 'body':
            case 'filehash':
                return preg_match($value, $this->post[$condition]);
            case 'body_reg':
                return $this->matchBodyReg($value);
            case 'filename':
            case 'extension':
                return $this->matchFileCondition($value, $condition);
            case 'ip':
                return preg_match($value, $_SERVER['REMOTE_ADDR']);
            case 'rdns':
                return preg_match($value, gethostbyaddr($_SERVER['REMOTE_ADDR']));
            case 'agent':
                return in_array($_SERVER['HTTP_USER_AGENT'], $value);
            case 'op':
            case 'has_file':
            case 'board':
            case 'password':
                return $this->post[$condition] == $value;
            default:
                throw new InvalidArgumentException('Unknown filter condition: ' . $condition);
        }
    }

    private function matchCustom(callable $callback): bool
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('Custom condition for filter is not callable!');
        }
        return $callback($this->post);
    }

    private function matchBodyReg(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $this->post['body_nomarkup'])) {
                return true;
            }
        }
        return false;
    }

    private function matchFileCondition($value, $condition): bool
    {
        if (empty($this->post['files'])) {
            return false;
        }
        foreach ($this->post['files'] as $file) {
            if (preg_match($value, $file[$condition])) {
                return true;
            }
        }
        return false;
    }

    public function applyAction(): void
    {
        $this->filterAction->execute($this->post);
    }
}

class FilterRule
{
    private array $condition;
    private FilterAction $action;

    public function __construct(array $condition, FilterAction $action)
    {
        $this->condition = $condition;
        $this->action = $action;
    }

    public function getCondition(): array
    {
        return $this->condition;
    }

    public function getAction(): FilterAction
    {
        return $this->action;
    }
}

class FilterAction
{
    private string $action;
    private ?string $message;
    private ?bool $addNote;
    private ?bool $allBoards;
    private ?int $expires;
    private ?string $reason;
    private ?bool $reject;
    private ?bool $banCookie;

    public function __construct(array $params)
    {
        $this->action = $params['action'] ?? 'reject';
        $this->message = $params['message'] ?? null;
        $this->addNote = $params['addNote'] ?? false;
        $this->allBoards = $params['allBoards'] ?? false;
        $this->expires = $params['expires'] ?? null;
        $this->reason = $params['reason'] ?? null;
        $this->reject = $params['reject'] ?? true;
        $this->banCookie = $params['banCookie'] ?? false;
    }

    public function execute(array $post): void
    {
        if ($this->addNote) {
            $this->addNoteToIP($post['body']);
        }

        switch ($this->action) {
            case 'reject':
                $this->reject();
                break;
            case 'ban':
                $this->ban($post['board'], $post['body']);
                break;
            default:
                throw new InvalidArgumentException('Unknown filter action: ' . $this->action);
        }
    }

    private function addNoteToIP(string $body): void
    {
        $query = prepare('INSERT INTO ``ip_notes`` (`ip`, `mod`, `time`, `body`) VALUES (:ip, :mod, :time, :body)');
        $query->bindValue(':ip', get_ip_hash($_SERVER['REMOTE_ADDR']));
        $query->bindValue(':mod', -1);
        $query->bindValue(':time', time());
        $query->bindValue(':body', "Autoban message: " . $body);
        $query->execute() or error(db_error($query));
    }

    private function reject(): void
    {
        error($this->message ?? 'Posting throttled by filter.');
    }

    private function ban(string $boardUri): void
    {
        if (empty($this->reason)) {
            throw new InvalidArgumentException('The ban action requires a reason.');
        }

        $banId = Bans::new_ban(get_ip_hash($_SERVER['REMOTE_ADDR']), get_uuser_cookie(), $this->reason, $this->expires, $this->allBoards ? false : $boardUri, -1);

        if ($this->banCookie) {
            Bans::ban_cookie($banId);
        }

        if ($this->reject) {
            error($this->message ?? _('You have been banned. <a href="/banned.php">Click here to view.</a>'));
        }
    }
}

class FloodChecker
{
    private array $floodCheck;

    public function __construct(array $floodCheck)
    {
        $this->floodCheck = $floodCheck;
    }

    public function match(array $match, array $post): bool
    {
        $filteredFloodCheck = array_filter(
            $this->floodCheck,
            function ($floodPost) use ($match, $post) {
                foreach ($match as $floodMatchArg) {
                    if (!$this->checkFloodCondition($floodMatchArg, $floodPost, $post)) {
                        return false;
                    }
                }
                return true;
            }
        );
        return !empty($filteredFloodCheck);
    }

    public function isFloodTime(int $time): bool
    {
        foreach ($this->floodCheck as $floodPost) {
            if (time() - $floodPost['time'] <= $time) {
                return true;
            }
        }
        return false;
    }

    public function isFloodCount(int $threshold, int $timeLimit): bool
    {
        $currentCount = count(array_filter(
            $this->floodCheck,
            function ($floodPost) use ($timeLimit) {
                return time() - $floodPost['time'] <= $timeLimit;
            }
        ));

        return $currentCount >= $threshold;
    }

    private function checkFloodCondition(string $condition, array $floodPost, array $post): bool
    {
        switch ($condition) {
            case 'ip':
                return $floodPost['ip'] == $_SERVER['REMOTE_ADDR'];
            case 'body':
                return $floodPost['posthash'] == make_comment_hex($post['body_nomarkup']);
            case 'file':
                return isset($post['filehash']) && $floodPost['filehash'] == $post['filehash'];
            case 'board':
                return $floodPost['board'] == $post['board'];
            case 'isreply':
                return $floodPost['isreply'] == $post['op'];
            default:
                throw new InvalidArgumentException('Invalid filter flood condition: ' . $condition);
        }
    }
}

function purge_flood_table(array $config): void
{
    $max_time = $config['flood_cache'] ?? max(array_map(fn ($filter) => $filter['condition']['flood-time'] ?? 0, $config['filters']));

    $time = time() - $max_time;

    query("DELETE FROM ``flood`` WHERE `time` < $time") or error(db_error());
}

function do_filters(array $post, array $config): void
{
    if (empty($config['filters'])) {
        return;
    }

    $floodCheck = getFloodCheck($post, $config);

    foreach ($config['filters'] as $filterConfig) {
        $floodChecker = new FloodChecker($floodCheck);
        $filterAction = new FilterAction($filterConfig);
        $filter = new Filter($filterConfig['condition'], $post, $floodChecker, $filterAction);

        if ($filter->check()) {
            $filter->applyAction();
        }
    }

    purge_flood_table($config);
}

function getFloodCheck(array $post, array $config): array
{
    if (array_search('flood-match', array_column($config['filters'], 'condition'))) {
        $query = prepareFloodQuery($post);
        $query->execute() or error(db_error($query));
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    return [];
}

function prepareFloodQuery(array $post): PDOStatement
{
    $queryStr = $post['has_file']
        ? "SELECT * FROM ``flood`` WHERE `ip` = :ip OR `posthash` = :posthash OR `filehash` = :filehash"
        : "SELECT * FROM ``flood`` WHERE `ip` = :ip OR `posthash` = :posthash";

    $query = prepare($queryStr);
    $query->bindValue(':ip', get_ip_hash($_SERVER['REMOTE_ADDR']));
    $query->bindValue(':posthash', make_comment_hex($post['body_nomarkup']));

    if ($post['has_file']) {
        $query->bindValue(':filehash', $post['filehash']);
    }

    return $query;
}
