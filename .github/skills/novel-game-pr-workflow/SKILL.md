---
name: novel-game-pr-workflow
description: 'GitHub PR review workflow for this repository. Use when reviewing pull requests, following anchor comment based review, checking reply comments and commit ranges, preparing or posting @copilot PR comments, or handling repository-specific PR and remote operation rules.'
argument-hint: 'レビュー、PRコメント、anchor comment 運用のどれを行うかを指定'
---

# Novel Game PR Workflow

この skill は、このリポジトリ固有の PR レビュー運用、PR コメント投稿運用、ブランチ制約を必要なときだけ読み込むためのものです。

## 使う場面

- PR のレビューを依頼されたとき
- GitHub 上のコメントを基準にレビュー対象コミットを絞るとき
- `@copilot` を使う PR コメント文面を作成または投稿するとき
- PR の base ブランチ、push 可否、ローカル作業範囲を確認するとき

## 手順

1. PR のレビューを行う場合は [review workflow](./references/review-workflow.md) を参照します。
2. `@copilot` コメントを作成または投稿する場合は [copilot comment rules](./references/copilot-comment-rules.md) を参照します。
3. 通常の実装規約や WordPress コーディング規約は workspace instructions に従います。

## 最低限の確認項目

- 新規 PR の base は `dev` であること
- 明示指示がない限り push、force-push、PR コメント投稿をしないこと
- 「ローカル」指示の場合はローカル作業で完結すること
- ローカル作業完了時に「リモート操作は実行していません」と報告すること