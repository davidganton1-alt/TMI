<?php
/**
 * The Miner Island — Factory Updates model
 *
 * Database layer for the oc_factory_update table (created in Phase 7 Step 1).
 * Read-only — admin CRUD is deferred to Phase 7.1.
 *
 * Methods:
 *   getTotalPosts()                 → total count of published posts
 *   getPosts($offset, $limit)       → paginated list, newest first
 *   getPost($post_id)               → single post by id (must be published)
 *   getPrevPost($post_id, $date)    → next-older post for prev-link nav
 *   getNextPost($post_id, $date)    → next-newer post for next-link nav
 *   getLatestPost()                 → newest single post (used by homepage
 *                                     trust pair in Step 5)
 */
class ModelExtensionModuleFactoryUpdates extends Model {

	public function getTotalPosts($category = '') {
		$where = "status = '1' AND published_at <= NOW()";
		if ($category !== '') {
			$where .= " AND category = '" . $this->db->escape($category) . "'";
		}
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "factory_update WHERE " . $where);
		return (int)$query->row['total'];
	}

	public function getPosts($offset = 0, $limit = 12, $category = '') {
		$offset = max(0, (int)$offset);
		$limit  = max(1, (int)$limit);

		$where = "status = '1' AND published_at <= NOW()";
		if ($category !== '') {
			$where .= " AND category = '" . $this->db->escape($category) . "'";
		}

		$query = $this->db->query("
			SELECT factory_post_id, slug, title, excerpt, hero_image_url, published_at, author_name, category
			FROM " . DB_PREFIX . "factory_update
			WHERE " . $where . "
			ORDER BY published_at DESC, factory_post_id DESC
			LIMIT " . $offset . ", " . $limit . "
		");

		return $query->rows;
	}

	/**
	 * Distinct non-empty categories among published posts, for the filter bar.
	 */
	public function getCategories() {
		$query = $this->db->query("
			SELECT category, COUNT(*) AS total
			FROM " . DB_PREFIX . "factory_update
			WHERE status = '1' AND published_at <= NOW() AND category <> ''
			GROUP BY category
			ORDER BY category ASC
		");
		return $query->rows;
	}

	public function getPost($post_id) {
		$post_id = (int)$post_id;
		$query = $this->db->query("
			SELECT *
			FROM " . DB_PREFIX . "factory_update
			WHERE factory_post_id = '" . $post_id . "'
			  AND status = '1'
			  AND published_at <= NOW()
			LIMIT 1
		");

		return $query->num_rows ? $query->row : null;
	}

	/**
	 * Get the next-OLDER post for "previous post" link. We order by
	 * published_at DESC in listing, so "previous" in nav-terms = "older".
	 */
	public function getPrevPost($post_id, $current_date) {
		$post_id = (int)$post_id;
		$current_date = $this->db->escape($current_date);
		$query = $this->db->query("
			SELECT factory_post_id, slug, title
			FROM " . DB_PREFIX . "factory_update
			WHERE status = '1'
			  AND published_at <= NOW()
			  AND (published_at < '" . $current_date . "'
			       OR (published_at = '" . $current_date . "' AND factory_post_id < '" . $post_id . "'))
			ORDER BY published_at DESC, factory_post_id DESC
			LIMIT 1
		");

		return $query->num_rows ? $query->row : null;
	}

	/**
	 * Get the next-NEWER post for "next post" link.
	 */
	public function getNextPost($post_id, $current_date) {
		$post_id = (int)$post_id;
		$current_date = $this->db->escape($current_date);
		$query = $this->db->query("
			SELECT factory_post_id, slug, title
			FROM " . DB_PREFIX . "factory_update
			WHERE status = '1'
			  AND published_at <= NOW()
			  AND (published_at > '" . $current_date . "'
			       OR (published_at = '" . $current_date . "' AND factory_post_id > '" . $post_id . "'))
			ORDER BY published_at ASC, factory_post_id ASC
			LIMIT 1
		");

		return $query->num_rows ? $query->row : null;
	}

	/**
	 * Get the single most-recent post. Used by the homepage trust pair in
	 * Step 5 to auto-pull the "Latest from the factory" card.
	 */
	public function getLatestPost() {
		$query = $this->db->query("
			SELECT factory_post_id, slug, title, excerpt, hero_image_url, published_at
			FROM " . DB_PREFIX . "factory_update
			WHERE status = '1' AND published_at <= NOW()
			ORDER BY published_at DESC, factory_post_id DESC
			LIMIT 1
		");

		return $query->num_rows ? $query->row : null;
	}
}
