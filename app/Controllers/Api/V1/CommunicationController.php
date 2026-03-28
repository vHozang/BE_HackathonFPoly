<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\NewsRead;
use App\Models\Policy;
use App\Models\PolicyAcknowledgment;

class CommunicationController extends Controller
{
    private NewsCategory $categories;
    private News $news;
    private NewsRead $newsReads;
    private Policy $policies;
    private PolicyAcknowledgment $policyAcknowledgments;

    public function __construct()
    {
        $this->categories = new NewsCategory();
        $this->news = new News();
        $this->newsReads = new NewsRead();
        $this->policies = new Policy();
        $this->policyAcknowledgments = new PolicyAcknowledgment();
    }

    public function categoryIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $search = $request->query('q');
        $result = $this->categories->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($search) ? $search : null
        );
        return $this->ok(
            $result['items'],
            'News category list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function categoryShow(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->categories->find($id);
        if ($item === null) {
            throw new HttpException('News category not found', 404, 'not_found');
        }
        return $this->ok($item, 'News category detail');
    }

    public function categoryStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'category_code' => ['required', 'string'],
            'category_name' => ['required', 'string'],
            'description' => ['string'],
            'status' => ['boolean'],
        ]);
        $id = $this->categories->create($payload);
        return $this->created($this->categories->find($id), 'News category created');
    }

    public function categoryUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($this->categories->find($id) === null) {
            throw new HttpException('News category not found', 404, 'not_found');
        }
        $payload = Validator::validate($request->all(), [
            'category_name' => ['string'],
            'description' => ['string'],
            'status' => ['boolean'],
        ]);
        $this->categories->updateById($id, $payload);
        return $this->ok($this->categories->find($id), 'News category updated');
    }

    public function categoryDelete(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($this->categories->find($id) === null) {
            throw new HttpException('News category not found', 404, 'not_found');
        }
        $this->categories->deleteById($id);
        return $this->ok(null, 'News category deleted');
    }

    public function newsIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $search = $request->query('q');
        $status = $request->query('status');
        $result = $this->news->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($search) ? $search : null,
            is_string($status) ? $status : null
        );
        return $this->ok(
            $result['items'],
            'News list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function newsShow(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->news->find($id);
        if ($item === null) {
            throw new HttpException('News not found', 404, 'not_found');
        }
        return $this->ok($item, 'News detail');
    }

    public function newsStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'news_code' => ['string'],
            'category_id' => ['integer'],
            'title' => ['required', 'string'],
            'summary' => ['string'],
            'content' => ['string'],
            'priority' => ['string'],
            'is_important' => ['boolean'],
            'is_pinned' => ['boolean'],
            'published_date' => ['date'],
            'expiry_date' => ['date'],
            'published_by' => ['integer'],
            'department_id' => ['integer'],
            'position_id' => ['integer'],
            'attachment_url' => ['string'],
            'image_url' => ['string'],
            'status' => ['string'],
        ]);

        $authUser = $request->attribute('auth_user');
        if (!isset($payload['news_code'])) {
            $payload['news_code'] = 'NEWS-' . date('Ymd-His') . '-' . random_int(100, 999);
        }
        $payload['published_by'] = (int) ($payload['published_by'] ?? ($authUser['employee_id'] ?? 1));
        $payload['created_by'] = (int) ($authUser['employee_id'] ?? 1);
        $payload['updated_by'] = (int) ($authUser['employee_id'] ?? 1);

        $id = $this->news->create($payload);
        return $this->created($this->news->find($id), 'News created');
    }

    public function newsUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($this->news->find($id) === null) {
            throw new HttpException('News not found', 404, 'not_found');
        }
        $payload = Validator::validate($request->all(), [
            'category_id' => ['integer'],
            'title' => ['string'],
            'summary' => ['string'],
            'content' => ['string'],
            'priority' => ['string'],
            'is_important' => ['boolean'],
            'is_pinned' => ['boolean'],
            'published_date' => ['date'],
            'expiry_date' => ['date'],
            'published_by' => ['integer'],
            'department_id' => ['integer'],
            'position_id' => ['integer'],
            'attachment_url' => ['string'],
            'image_url' => ['string'],
            'status' => ['string'],
        ]);
        $authUser = $request->attribute('auth_user');
        $payload['updated_by'] = (int) ($authUser['employee_id'] ?? 1);
        $this->news->updateById($id, $payload);
        return $this->ok($this->news->find($id), 'News updated');
    }

    public function newsDelete(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($this->news->find($id) === null) {
            throw new HttpException('News not found', 404, 'not_found');
        }
        $this->news->deleteById($id);
        return $this->ok(null, 'News deleted');
    }

    public function newsMarkRead(Request $request, array $params): array
    {
        $newsId = (int) ($params['id'] ?? 0);
        if ($this->news->find($newsId) === null) {
            throw new HttpException('News not found', 404, 'not_found');
        }
        $authUser = $request->attribute('auth_user');
        $employeeId = (int) ($authUser['employee_id'] ?? 0);

        $db = Database::connection();
        $sql = "INSERT INTO news_reads (news_id, employee_id, ip_address, device_info)
                VALUES (:news_id, :employee_id, :ip_address, :device_info)
                ON DUPLICATE KEY UPDATE read_date = CURRENT_TIMESTAMP";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'news_id' => $newsId,
            'employee_id' => $employeeId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'device_info' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        $db->prepare('UPDATE news SET view_count = view_count + 1 WHERE news_id = :news_id')
            ->execute(['news_id' => $newsId]);

        return $this->ok(null, 'News marked as read');
    }

    public function policyIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $search = $request->query('q');
        $status = $request->query('status');
        $result = $this->policies->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($search) ? $search : null,
            is_string($status) ? $status : null
        );
        return $this->ok(
            $result['items'],
            'Policy list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function policyShow(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->policies->find($id);
        if ($item === null) {
            throw new HttpException('Policy not found', 404, 'not_found');
        }
        return $this->ok($item, 'Policy detail');
    }

    public function policyStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'policy_code' => ['string'],
            'policy_name' => ['required', 'string'],
            'policy_type' => ['required', 'string'],
            'content' => ['string'],
            'version' => ['string'],
            'effective_date' => ['date'],
            'expiry_date' => ['date'],
            'department_id' => ['integer'],
            'file_url' => ['string'],
            'is_required_acknowledgment' => ['boolean'],
            'status' => ['string'],
        ]);
        if (!isset($payload['policy_code'])) {
            $payload['policy_code'] = 'POL-' . date('Ymd-His') . '-' . random_int(100, 999);
        }
        $authUser = $request->attribute('auth_user');
        $payload['created_by'] = (int) ($authUser['employee_id'] ?? 1);
        $id = $this->policies->create($payload);
        return $this->created($this->policies->find($id), 'Policy created');
    }

    public function policyUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($this->policies->find($id) === null) {
            throw new HttpException('Policy not found', 404, 'not_found');
        }
        $payload = Validator::validate($request->all(), [
            'policy_name' => ['string'],
            'policy_type' => ['string'],
            'content' => ['string'],
            'version' => ['string'],
            'effective_date' => ['date'],
            'expiry_date' => ['date'],
            'department_id' => ['integer'],
            'file_url' => ['string'],
            'is_required_acknowledgment' => ['boolean'],
            'status' => ['string'],
            'approved_by' => ['integer'],
            'approved_date' => ['date'],
        ]);
        $this->policies->updateById($id, $payload);
        return $this->ok($this->policies->find($id), 'Policy updated');
    }

    public function policyDelete(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($this->policies->find($id) === null) {
            throw new HttpException('Policy not found', 404, 'not_found');
        }
        $this->policies->deleteById($id);
        return $this->ok(null, 'Policy deleted');
    }

    public function policyAcknowledge(Request $request, array $params): array
    {
        $policyId = (int) ($params['id'] ?? 0);
        if ($this->policies->find($policyId) === null) {
            throw new HttpException('Policy not found', 404, 'not_found');
        }
        $authUser = $request->attribute('auth_user');
        $employeeId = (int) ($authUser['employee_id'] ?? 0);

        $sql = "INSERT INTO policy_acknowledgments (policy_id, employee_id, ip_address)
                VALUES (:policy_id, :employee_id, :ip_address)
                ON DUPLICATE KEY UPDATE acknowledged_date = CURRENT_TIMESTAMP";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'policy_id' => $policyId,
            'employee_id' => $employeeId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        return $this->ok(null, 'Policy acknowledged');
    }
}
